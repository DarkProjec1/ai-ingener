# Бот поддержки акции «Вкусная осень» (MVP)

Telegram-бот + веб-панель операторов для промо-акции йогуртов «Молочный край».

Стек: **PHP 8.3 / Laravel / PostgreSQL / Docker**.

## Быстрый старт

```bash
cp .env.example .env
# Заполнить TELEGRAM_BOT_TOKEN, LLM_API_KEY (опционально)

docker compose up --build
```

- Панель: http://localhost:8000/login  
  Логин: `operator@example.com` / `password`
- Бот: long-polling в сервисе `bot`

### Без Docker (SQLite)

```bash
composer install
cp .env.example .env
# DB_CONNECTION=sqlite
touch database/database.sqlite
php artisan key:generate
php artisan migrate --seed
php artisan serve
# другой терминал:
php artisan telegram:poll
```

При `LLM_API_KEY=test` работает rule-based движок (все 25 кейсов из requests.md).

## Допущения

См. ответы на блокеры в ТЗ + README ниже:
1. Нет доступа к данным участников → эскалация на персональные вопросы.
2. Ответ оператора через sendMessage; при блоке бота — недоставляемо.
3. Auth: 1 оператор, без ролей.
4. Время ответа: от создания тикета до first_response_at.
5. Приоритизации нет.
6. Только вопросы по акции.
7. Инъекции — отказ.
8. Медиа — отказ.
9. Полная история в тикете.
10. Только русский.

## Промпты

- `prompts/system_prompt.md`
- `prompts/escalate_prompt.md`
- `prompts/promo-rules.md`

## Прогон

`docs/requests-run.md`

## Схема БД

`docs/schema.md`

## Перед продакшеном

Webhook, уведомления операторам, rate-limit, мониторинг LLM, бэкапы.
