<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramResponseException;

class SendTelegramPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $fromChatId,
        public int $messageId,
        public int $adminChatId,
        public int $adminMsgId
    ) {}

    public function handle(): void
    {
        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));

        $telegramIds = User::whereNotNull('telegram_id')->pluck('telegram_id');
        $activeCount = 0;

        foreach ($telegramIds as $chatId) {
            try {
                $telegram->copyMessage([
                    'chat_id' => $chatId,
                    'from_chat_id' => $this->fromChatId,
                    'message_id' => $this->messageId,
                ]);
                $activeCount++;
            } catch (\Exception $e) {
                Log::error("❌ Post yuborilmadi: {$chatId} - " . $e->getMessage());
            }
        }

        // Klaviatura
        $keyboard = [
            ['💎 Do‘stlarni taklif qilish'],
            ['📣 Mening do‘stlarim', '📊 Natijalar'],
            ['🎁 Sovg‘alar', '📝 Tanlov shartlari'],
        ];
        $adminUser = User::where('telegram_id', $this->adminChatId)->first();
        if ($adminUser && $adminUser->is_admin) {
            $keyboard[] = ['📬 Post yaratish'];
        }

        // "Jo‘natilyapti..." degan status xabarini o‘chiramiz
        try {
            $telegram->deleteMessage([
                'chat_id' => $this->adminChatId,
                'message_id' => $this->adminMsgId,
            ]);
        } catch (TelegramResponseException $e) {
            Log::warning("❗ Status xabarni o‘chirib bo‘lmadi: " . $e->getMessage());
        }

        // Yangi status xabar yuboriladi
        try {
            $telegram->sendMessage([
                'chat_id' => $this->adminChatId,
                'text' => "✅ Post yuborildi!\nAktiv foydalanuvchilar soni: {$activeCount} ta",
                'reply_markup' => json_encode([
                    'keyboard' => $keyboard,
                    'resize_keyboard' => true,
                    'one_time_keyboard' => false,
                ], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Exception $e) {
            Log::error("❌ Yangi status xabar yuborilmadi: " . $e->getMessage());
        }
    }
}
