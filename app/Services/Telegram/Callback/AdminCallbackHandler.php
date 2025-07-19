<?php

namespace App\Services\Telegram\Callback;

use Telegram\Bot\Objects\Update;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AdminCallbackHandler
{
    public function handle(Update $update, User $user): void
    {
        $callbackQuery = $update->getCallbackQuery();
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $data = $callbackQuery->getData();
        switch ($data) {
            case 'cancel_post':
                $user->step = null;
                $user->save();
                $msgId = Cache::get("post_message_id_$chatId");
                Telegram::deleteMessage([
                    'chat_id' => $chatId,
                    'message_id' => $msgId,
                ]);
                Cache::forget("post_message_id_$chatId");
                $keyboard = [
                    ['💎 Do‘stlarni taklif qilish'],
                    ['📣 Mening do‘stlarim', '📊 Natijalar'],
                    ['🎁 Sovg‘alar', '📝 Tanlov shartlari'],
                ];

                if ($user->is_admin) {
                    $keyboard[] = ['📬 Post yaratish']; // adminlar uchun qo‘shimcha tugma
                }
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "❌ Post yuborish bekor qilindi.",
                    'reply_markup' => json_encode([
                                                        'keyboard' => $keyboard,
                                                        'resize_keyboard' => true,
                                                        'one_time_keyboard' => false,
                                                    ]),
                ]);
                break;

            // boshqa holatlar uchun case'lar shu yerda bo'lishi mumkin
        }
    }
}
