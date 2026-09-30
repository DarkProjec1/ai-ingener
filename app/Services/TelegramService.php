<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected string $token;
    protected string $apiUrl;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token', '');
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}";
    }

    public function sendMessage(int|string $chatId, string $text, array $extra = []): bool
    {
        if (empty($this->token) || $this->token === 'test') {
            Log::info("[Telegram mock] To {$chatId}: {$text}");
            return true;
        }

        try {
            $payload = array_merge([
                'chat_id' => $chatId,
                'text' => mb_substr($text, 0, 4096),
                'parse_mode' => 'HTML',
            ], $extra);

            $response = Http::timeout(15)->post("{$this->apiUrl}/sendMessage", $payload);

            if (!$response->successful()) {
                Log::error('Telegram sendMessage failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::error('Telegram exception: ' . $e->getMessage());
            return false;
        }
    }

    public function getUpdates(int $offset = 0, int $timeout = 25): array
    {
        if (empty($this->token) || $this->token === 'test') {
            return [];
        }

        try {
            $response = Http::timeout($timeout + 5)->get("{$this->apiUrl}/getUpdates", [
                'offset' => $offset,
                'timeout' => $timeout,
                'allowed_updates' => ['message'],
            ]);

            if (!$response->successful()) {
                Log::error('Telegram getUpdates failed', ['body' => $response->body()]);
                return [];
            }

            return $response->json('result', []);
        } catch (\Throwable $e) {
            Log::error('Telegram getUpdates exception: ' . $e->getMessage());
            return [];
        }
    }

    public function answerCallbackQuery(string $callbackId, string $text = ''): void
    {
        if (empty($this->token) || $this->token === 'test') {
            return;
        }
        Http::post("{$this->apiUrl}/answerCallbackQuery", [
            'callback_query_id' => $callbackId,
            'text' => $text,
        ]);
    }
}
