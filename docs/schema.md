# Схема базы данных

```mermaid
erDiagram
    users ||--o{ tickets : "operator"
    participants ||--o{ messages : has
    participants ||--o{ tickets : opens
    participants ||--o{ llm_logs : generates
    tickets ||--o{ messages : contains

    users {
        bigint id PK
        string name
        string email
        string password
        boolean is_operator
        timestamps
    }

    participants {
        bigint id PK
        bigint telegram_id UK
        string username
        string first_name
        string last_name
        string phone
        timestamps
    }

    tickets {
        bigint id PK
        bigint participant_id FK
        bigint operator_id FK
        enum status "open|closed"
        string subject
        text last_user_message
        timestamp first_response_at
        timestamp closed_at
        timestamps
    }

    messages {
        bigint id PK
        bigint participant_id FK
        enum role "user|bot|operator"
        text content
        boolean is_media
        bigint ticket_id FK
        timestamps
    }

    llm_logs {
        bigint id PK
        bigint participant_id FK
        text user_message
        json llm_response
        boolean escalated
        int tokens_used
        int latency_ms
        string model
        timestamps
    }
```

## Ключевые решения

1. **participants** отделены от `users` — участники акции не логинятся в веб, только Telegram ID.
2. **messages** хранят всю переписку; `ticket_id` заполняется только для сообщений в контексте обращения.
3. **tickets.first_response_at** — для метрики «среднее время ответа оператора» (от создания тикета).
4. **llm_logs** — аудит решений бота (в т.ч. rule-based fallback) и попыток инъекций.
5. Оператор — обычный `users` с флагом `is_operator`.
