# maximaster/bitrix-codeception

Модуль Codeception для запуска тестов использующих Битрикс.

Оборачивает запуск каждого теста в транзацию на уровне коннекта Битрикса к базе,
и после завершения теста делает ROLLBACK. Как следствие, ваши тесты не будут
влиять на данные в базе и их можно запукать повторно без её восстановления.

Пример подключения в **some.suit.yaml**:

```yaml
actor: SomeTester
modules:
    enabled:
        - \Maximaster\BitrixCodeception\TransactionWrappperModule
```
