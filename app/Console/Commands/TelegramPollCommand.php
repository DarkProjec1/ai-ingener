<?php

namespace App\Console\Commands;

use App\Services\BotService;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll';
    protected $description = 'Long-polling Telegram bot updates';

    public function handle(TelegramService $telegram, BotService $bot): int
    {
        $this->info('Telegram long-polling started...');
        $offset = 0;

        while (true) {
            try {
                $updates = $telegram->getUpdates($offset, 25);
                foreach ($updates as $update) {
                    $offset = ($update['update_id'] ?? 0) + 1;
                    $bot->handleUpdate($update);
                }
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
                sleep(3);
            }
        }

        return self::SUCCESS;
    }
}
