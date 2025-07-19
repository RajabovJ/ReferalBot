<?php

namespace App\Http\Controllers;

use App\Models\Text;
use App\Models\User;
use App\Services\Telegram\MainTelegramHandler;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramBotController extends Controller
{
    public function handle()
    {
            try
            {
                $update = Telegram::getWebhookUpdate();
                $chatId = null;
                $dataType = null;

                if ($update->isType('message')) {
                    $chatId = $update->getMessage()->getChat()->getId();
                    $message = $update->getMessage();
                    $dataType = 'message';
                    $chatType = $message->getChat()->getType(); // 🧩 chat turi
                } elseif ($update->isType('callback_query')) {
                    $callback = $update->getCallbackQuery();
                    $chatId = $callback->getMessage()->getChat()->getId();
                    $chatType = $callback->getMessage()->getChat()->getType(); // 🧩 chat turi
                    $dataType = 'callback';
                }
                if (!in_array($chatType, ['private'])) {
                    return;
                }
                if (!$chatId)
                {
                    Log::warning("❗ Chat ID topilmadi.");
                    return;
                }

                if ($update->isType('message')) {
                    $text = $update->getMessage()->getText();

                    if (strpos($text, '/') === 0) {
                        // Faqat komandalar kelganida ishlaydi
                        Telegram::commandsHandler(true);
                    }
                }

                // 📥 Callback tugmasi bosilgan bo‘lsa (masalan, "✅ Tekshirish")

                if ($dataType === 'callback') {
                    $callbackData = $update->getCallbackQuery()->getData();

                    if ($callbackData === 'check_subscription') {
                        // A'zolikni tekshiramiz
                        if ($this->checkMembership($chatId)) {
                            // Foydalanuvchini bazadan olamiz
                            $user = User::where('telegram_id', $chatId)->first();

                            // referral_verified ni yangilaymiz
                            if ($user && !$user->referral_verified) {
                                $user->referral_verified = true;
                                $user->save();

                                // Uni taklif qilgan foydalanuvchini topamiz
                                $referrer = User::find($user->referrer_id);
                                if ($referrer && $referrer->referrals()->count() >= 50 && !$referrer->got_invite_link) {
                                    try {
                                        $chatId = env('PRIVATE_GROUP_ID');
                                        if (!$chatId) {
                                            Log::warning('❗ PRIVATE_GROUP_ID environmentda yo‘q');
                                            return;
                                        }

                                        $inviteLinkResponse = Telegram::createChatInviteLink([
                                            'chat_id' => $chatId,
                                            'member_limit' => 1,
                                            'creates_join_request' => false,
                                        ]);

                                        // Bu yerda obyekt sifatida olish kerak
                                        $inviteLink = $inviteLinkResponse?->invite_link ?? null;

                                        if ($inviteLink) {
                                            Telegram::sendMessage([
                                                'chat_id' => $referrer->telegram_id,
                                                'text' => "🎉 Tabriklaymiz! Siz 3 ta foydalanuvchini taklif qildingiz.\n\nYopiq guruhga kirish uchun havola:\n{$inviteLink}",
                                            ]);

                                            $referrer->got_invite_link = true;
                                            $referrer->save();

                                        } else {
                                            Log::warning("⚠️ Invite link topilmadi. Javob: " . json_encode($inviteLinkResponse));
                                        }

                                    } catch (\Telegram\Bot\Exceptions\TelegramSDKException $e) {
                                        Log::error("❌ TelegramSDKException: " . $e->getMessage());
                                    } catch (\Exception $e) {
                                        Log::error("❌ Invite link yaratishda boshqa xatolik: " . $e->getMessage());
                                    }
                                }

                                Log::info("✅ User #{$user->id} referral tasdiqlandi.");
                            }


                            // Referral havolasi
                            $botUsername = env('TELEGRAM_BOT_USERNAME', 'YourBotUsername');
                            $referralLink = "https://t\\.me/{$botUsername}?start\\={$chatId}";

                            // Matn shabloni
                            $template = Text::where('key', 'subscription_success')->value('text') ?? '';
                            $finalText = str_replace(
                                ['{referral_link}'],
                                [$referralLink],
                                $template
                            );
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
                                'text' => "*🎉 Xush kelibsiz\\!*\n\n" .
                                          "📢 Do‘stlaringizni ushbu botga taklif qiling va *ballar* to‘plang\\! Har bir taklif — bu siz uchun *yutuq sari bir qadam*\\.\n\n" .
                                          "🎁 Eng faol ishtirokchilar *qimmatbaho sovg‘alar* bilan taqdirlanadi: nishonlar, maqolalar va xalqaro sertifikatlar sizni kutmoqda\\!\n\n" .
                                          "👇 Do‘stlaringizni quyidagi havola orqali taklif qiling va ballarga ega bo‘ling 👇",
                                'parse_mode' => 'MarkdownV2',
                                'reply_markup' => json_encode([
                                    'keyboard' => $keyboard,
                                    'resize_keyboard' => true,
                                    'one_time_keyboard' => false,
                                ]),
                            ]);

                            Telegram::sendPhoto([
                                'chat_id' => $chatId,
                                'photo' => fopen(public_path('images/startuchun.png'), 'r'),
                                'caption' => $finalText,
                                'parse_mode' => 'MarkdownV2',
                                'reply_markup' => json_encode([
                                    'inline_keyboard' => [
                                        [
                                            [
                                                'text' => '🔗 Qo\'shilish',
                                                'url' => 'https://t.me/' . $botUsername . '?start=' . $chatId
                                            ]
                                        ]
                                    ]
                                ]),
                            ]);
                        } else {
                            $this->askToJoinChannels($chatId);
                        }

                        return; // ✅ Callback yakunlandi
                    }
                }





                // A’zolikni tekshirish
                if (!$this->checkMembership($chatId))
                {
                    $this->askToJoinChannels($chatId);
                    return;
                }
                $user = User::where('telegram_id', $chatId)->first();
                app(MainTelegramHandler::class)->handle($chatId);



            }
        catch (\Exception $e)
        {
            Log::error("Telegram handle xatolik: " . $e->getMessage());
        }
    }









    private function checkMembership(int $chatId): bool
    {
        foreach ($this->parseRequiredChannels() as $channel) {
            try {
                $member = Telegram::getChatMember([
                    'chat_id' => $channel['username'],
                    'user_id' => $chatId,
                ]);

                $status = $member->get('status');

                if (!in_array($status, ['member', 'administrator', 'creator'])) {
                    return false;
                }

            } catch (\Exception $e) {
                Log::error("❗ A’zolik tekshiruvida xatolik ({$channel['username']}): " . $e->getMessage());
                return false;
            }
        }
        $oldMsgId = cache()->get("sub_msg_{$chatId}");
        if ($oldMsgId) {
            Telegram::deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $oldMsgId,
            ]);
            cache()->forget("sub_msg_{$chatId}");

        }
        return true;
    }
    private function askToJoinChannels(int $chatId): void
    {
        $channels = $this->parseRequiredChannels();
        $text = "🛑 Botdan foydalanish uchun quyidagi kanallarga a’zo bo‘ling va '✅ Tekshirish' tugmasini bosing:";

        $buttons = [];

        foreach ($channels as $channel) {
            $text .= "\n📢 " . $channel['username'];
            $buttons[] = [[
                'text' => "🔗 " . $channel['username'],
                'url' => $channel['link'],
            ]];
        }

        $buttons[] = [[
            'text' => "✅ Tekshirish",
            'callback_data' => "check_subscription"
        ]];

        $response=Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]);
        $sentMessageId = $response->getMessageId();
        cache()->put("sub_msg_{$chatId}", $sentMessageId, now()->addMinutes(10));
    }
    private function parseRequiredChannels(): array
    {
        $channels = [];
        $raw = env('TELEGRAM_REQUIRED_CHANNELS', '');

        foreach (explode(',', $raw) as $username) {
            $username = trim($username);
            if ($username !== '') {
                $channels[] = [
                    'username' => $username,
                    'link' => 'https://t.me/' . ltrim($username, '@'),
                ];
            }
        }

        return $channels;
    }

}








