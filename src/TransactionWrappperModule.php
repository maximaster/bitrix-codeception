<?php

declare(strict_types=1);

namespace Maximaster\BitrixCodeception;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\SystemException;
use Codeception\Lib\Interfaces\DependsOnModule;
use Codeception\Lib\ModuleContainer;
use Codeception\Module;
use Codeception\TestInterface;
use Maximaster\BitrixLoader\BitrixLoader;
use Maximaster\BitrixSingleConnect\ProxyConnection;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\SemaphoreStore;

// phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
class TransactionWrappperModule extends Module implements DependsOnModule
{
    private Connection $connect;
    private LockFactory $lockFactory;

    public function _depends(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     *
     * @psalm-param array<mixed> $config
     */
    public function __construct(ModuleContainer $moduleContainer, $config = null)
    {
        parent::__construct($moduleContainer, $config);

        $this->lockFactory = new LockFactory(new SemaphoreStore());
    }

    /**
     * {@inheritDoc}
     *
     * @throws SystemException
     *
     * @SuppressWarnings(PHPMD.Superglobals) why:dependency
     * @SuppressWarnings(PHPMD.CamelCaseMethodName) why:dependency
     */
    public function _beforeSuite($settings = []): void
    {
        // TODO уточнить возможность и целесообразность вставки как отдельного
        //      модуля, а так же загрузки BitrixLoader из собственого провайдера
        //      который уже будет добавлять проектный код (брать из контейнера
        //      или как-то ещё).
        $loader = BitrixLoader::fromGuess();
        $loader->prologBefore();

        $application = Application::getInstance();
        $application->initializeBasicKernel();

        $connect = $application->getConnectionPool()->getConnection();
        if ($connect === null) {
            throw new RuntimeException('Не удалось получить данные о соединении');
        }

        $this->connect = $connect;

        // Подключаемся разово, если ещё не.
        if ($this->connect->isConnected() === false) {
            $this->connect->connect();
        }

        if (class_exists(ProxyConnection::class)) {
            $GLOBALS['DB'] = ProxyConnection::fromGlobal();
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws SystemException
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName) why:dependency
     * @SuppressWarnings(PHPMD.CamelCaseVariableName) why:dependency
     */
    public function _before(TestInterface $test): void
    {
        $this->connect->rollbackTransaction();
        $this->connect->startTransaction();

        $lock = $this->lockFactory->createLock(__METHOD__);
        $lock->acquire(true);

        global $USER;
        $USER->Logout();

        $managedCache = Application::getInstance()->getManagedCache();
        $managedCache->cleanAll();

        $lock->release();
    }

    /**
     * {@inheritDoc}
     *
     * @throws SqlQueryException
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName) why:dependency
     * @SuppressWarnings(PHPMD.CamelCaseVariableName) why:dependency
     */
    public function _after(TestInterface $test): void
    {
        $this->connect->rollbackTransaction();

        $lock = $this->lockFactory->createLock(__METHOD__);
        $lock->acquire(true);

        global $USER;
        $USER->Logout();

        $lock->release();
    }
}
