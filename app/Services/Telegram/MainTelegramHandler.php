<?php
namespace App\Services\Telegram;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\Update;
use App\Services\Telegram\Text\UserTextMessageHandler;
use App\Services\Telegram\Text\AdminTextMessageHandler;
use App\Services\Telegram\Callback\UserCallbackHandler;
use App\Services\Telegram\Callback\AdminCallbackHandler;

class MainTelegramHandler
{
    protected UserTextMessageHandler $userTextHandler;
    protected AdminTextMessageHandler $adminTextHandler;
    protected UserCallbackHandler $userCallbackHandler;
    protected AdminCallbackHandler $adminCallbackHandler;

    public function __construct(
        UserTextMessageHandler $userTextHandler,
        AdminTextMessageHandler $adminTextHandler,
        UserCallbackHandler $userCallbackHandler,
        AdminCallbackHandler $adminCallbackHandler
    ) {
        $this->userTextHandler = $userTextHandler;
        $this->adminTextHandler = $adminTextHandler;
        $this->userCallbackHandler = $userCallbackHandler;
        $this->adminCallbackHandler = $adminCallbackHandler;
    }

    public function handle($chatId): void
    {
        $update = Telegram::getWebhookUpdate();

        $dataType = null;

        if ($update->isType('message')) {
            $dataType = 'message';
        } elseif ($update->isType('callback_query')) {
            $dataType = 'callback';
        }

        if (!$chatId) {
            Log::warning("Telegram: chat_id topilmadi.");
            return;
        }

        $user = User::where('telegram_id', $chatId)->first();
        if (!$user) {
            Log::info("Telegram: Foydalanuvchi topilmadi. /start bilan ro‘yxatdan o‘tmagan.");
            return;
        }

        // Handlerlarni ishlatamiz
        if ($dataType === 'message') {
            if ($user->is_admin) {
                $this->adminTextHandler->handle($update, $user);
            } else {
                $this->userTextHandler->handle($update, $user);
            }
        } elseif ($dataType === 'callback') {
            if ($user->is_admin) {
                $this->adminCallbackHandler->handle($update, $user);
            } else {
                $this->userCallbackHandler->handle($update, $user);
            }
        }
    }
}
