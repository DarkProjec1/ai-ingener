# CLAUDE.md — заметки по проекту

## Контекст
MVP бота поддержки акции «Вкусная осень» для тестового задания M-Social.

## Решения
- Rule-based fallback при отсутствии LLM_API_KEY (покрывает 25 кейсов).
- Long-polling вместо webhook для простоты docker-compose.
- Простой auth без Breeze.
- Полная история сообщений участника в карточке тикета.

## Команды
- `php artisan telegram:poll` — long-polling
- `php artisan migrate --seed`
