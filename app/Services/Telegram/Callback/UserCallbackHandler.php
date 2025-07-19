<?php

namespace App\Services\Telegram\Callback;

use Telegram\Bot\Api;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\Update;
use App\Models\User;

class UserCallbackHandler
{
    public function handle(Update $update, User $user): void
    {
        $callbackQuery = $update->getCallbackQuery();
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $data = $callbackQuery->getData();

        if ($data === 'check_subscription') {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "✅ Sizning aʼzoligingiz muvaffaqiyatli tasdiqlandi!",
            ]);
        } else {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "ℹ️ Nomaʼlum tugma bosildi: {$data}",
            ]);
        }
    }
}
