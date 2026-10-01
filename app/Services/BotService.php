<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Participant;
use App\Models\Ticket;
use Illuminate\Support\Facades\Log;

class BotService
{
    public function __construct(
        protected LlmService $llm,
        protected TelegramService $telegram,
    ) {}

    public function handleUpdate(array $update): void
    {
        $message = $update['message'] ?? null;
        if (!$message) {
            return;
        }

        $chatId = $message['chat']['id'];
        $from = $message['from'] ?? [];
        $text = $message['text'] ?? null;
        $hasMedia = isset($message['photo']) || isset($message['voice']) || isset($message['document']) || isset($message['video']);

        $participant = Participant::firstOrCreate(
            ['telegram_id' => $from['id'] ?? $chatId],
            [
                'username' => $from['username'] ?? null,
                'first_name' => $from['first_name'] ?? null,
                'last_name' => $from['last_name'] ?? null,
            ]
        );

        if ($hasMedia || $text === null) {
            $this->saveMessage($participant, 'user', '[медиа-сообщение]', true);
            $reply = 'К сожалению, в текущей версии я принимаю только текстовые сообщения. Опишите, пожалуйста, ваш вопрос текстом.';
            $this->saveMessage($participant, 'bot', $reply);
            $this->telegram->sendMessage($chatId, $reply);
            return;
        }

        $text = trim($text);
        if ($text === '') {
            return;
        }

        // /start — сброс «залипания» на старом тикете не делаем, просто приветствие
        if (str_starts_with($text, '/start')) {
            $reply = "Здравствуйте! Я бот поддержки акции «Вкусная осень» бренда «Молочный край».\n\nЗадайте вопрос по правилам акции — срокам, продукции, призам, регистрации чеков.\n\nКоманды:\n/start — приветствие\n/new — начать новый диалог (если открыто обращение к оператору, бот снова отвечает сам на типовые вопросы)";
            $this->saveMessage($participant, 'user', $text);
            $this->saveMessage($participant, 'bot', $reply);
            $this->telegram->sendMessage($chatId, $reply);
            return;
        }

        // /new — пользователь хочет снова получать автоответы, не только «передано оператору»
        if (str_starts_with($text, '/new')) {
            $open = $participant->openTicket();
            if ($open) {
                $open->close();
            }
            $reply = 'Хорошо, продолжаем. Задайте вопрос по акции — отвечу по правилам. Если понадобится оператор, передам обращение.';
            $this->saveMessage($participant, 'user', $text);
            $this->saveMessage($participant, 'bot', $reply);
            $this->telegram->sendMessage($chatId, $reply);
            return;
        }

        $this->saveMessage($participant, 'user', $text);

        // Всегда сначала пытаемся ответить по правилам / LLM.
        // Открытый тикет НЕ блокирует автоответы на типовые вопросы.
        $history = $participant->messages()
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->reverse()
            ->map(fn ($m) => ['role' => $m->role === 'user' ? 'user' : 'assistant', 'content' => $m->content])
            ->values()
            ->all();

        $result = $this->llm->chat($text, $participant, $history);

        $answer = $result['answer'] ?? 'Не удалось сформировать ответ.';
        $escalate = (bool) ($result['escalate'] ?? false);

        $openTicket = $participant->openTicket();

        if ($escalate) {
            if ($openTicket) {
                $ticket = $openTicket;
                $ticket->update(['last_user_message' => $text]);
            } else {
                $ticket = $this->createTicket($participant, $text, $result['reason'] ?? null);
            }
            $this->saveMessage($participant, 'bot', $answer, false, $ticket->id);
            $answer .= "\n\n📨 Обращение #" . $ticket->id . " передано оператору. Ответ придёт в этот чат.\n(Типовые вопросы по правилам можно задавать дальше — бот ответит сам. Команда /new — сбросить обращение.)";
        } else {
            $this->saveMessage($participant, 'bot', $answer);
        }

        $this->telegram->sendMessage($chatId, $answer);
    }

    public function sendOperatorReply(Ticket $ticket, string $text, int $operatorId): void
    {
        $participant = $ticket->participant;
        $ticket->markFirstResponse();
        if (!$ticket->operator_id) {
            $ticket->update(['operator_id' => $operatorId]);
        }

        $this->saveMessage($participant, 'operator', $text, false, $ticket->id);
        $this->telegram->sendMessage($participant->telegram_id, "💬 Ответ оператора:\n\n" . $text);
    }

    protected function createTicket(Participant $participant, string $lastMessage, ?string $reason = null): Ticket
    {
        return Ticket::create([
            'participant_id' => $participant->id,
            'status' => 'open',
            'subject' => $reason ? mb_substr($reason, 0, 200) : mb_substr($lastMessage, 0, 100),
            'last_user_message' => $lastMessage,
        ]);
    }

    protected function saveMessage(
        Participant $participant,
        string $role,
        string $content,
        bool $isMedia = false,
        ?int $ticketId = null
    ): Message {
        return Message::create([
            'participant_id' => $participant->id,
            'role' => $role,
            'content' => $content,
            'is_media' => $isMedia,
            'ticket_id' => $ticketId,
        ]);
    }
}
