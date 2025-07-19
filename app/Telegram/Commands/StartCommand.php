<?php

namespace App\Telegram\Commands;

use App\Models\Text;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;

class StartCommand extends Command
{
    protected string $name = 'start';
    protected string $description = 'Boshlanish komandasi';

    public function handle()
    {
        $update = $this->getUpdate();

        $from = $update->getMessage()?->getFrom();

        if (!$from || $from->isBot()) {
            Log::warning("StartCommand: Foydalanuvchi topilmadi yoki bu bot edi.");
            return;
        }

        $telegramId = $from->getId();
        $username = $from->getUsername();
        $firstName = $from->getFirstName();
        $lastName = $from->getLastName();

        // /start buyrug‘ida referal ID bo‘lishi mumkin: /start 123
        $text = $update->getMessage()->getText();
        $referrerId = null;

        if (preg_match('/^\/start\s+(\d+)/', $text, $matches)) {
            $referrerTelegramId = (int) $matches[1];

            // Referal foydalanuvchini topamiz
            $referrer = User::where('telegram_id', $referrerTelegramId)->first();

            if ($referrer && $referrer->telegram_id != $telegramId) {
                $referrerId = $referrer->id;
            }
        }

        // Foydalanuvchini ro‘yxatdan o‘tkazamiz yoki topamiz
        $user = User::firstOrCreate(
            ['telegram_id' => $telegramId],
            [
                'username' => $username,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'referrer_id' => $referrerId,
            ]
        );
        $chatId=$telegramId;
        if ($this->checkMembership($chatId)) {
            // 👉 Foydalanuvchini topamiz va referral_verified ni true qilamiz
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


            // 📣 Referral havolasini yaratamiz
            $botUsername = env('TELEGRAM_BOT_USERNAME', 'YourBotUsername');
            $referralLink = "https://t\\.me/{$botUsername}?start\\={$chatId}";
            $template = Text::where('key', 'subscription_success')->value('text') ?? '';
            // Matn shabloni
            $template = Text::where('key', 'subscription_success')->value('text') ?? '';
            $finalText = str_replace(
                ['{referral_link}'],
                [$referralLink],
                $template
            );
            // Telegram::sendDocument([
            //     'chat_id' => $chatId,
            //     'document' => InputFile::create(public_path('images/startuchun.jpg')),
            //     'caption' => $finalText,
            //     'parse_mode' => 'MarkdownV2',
            // ]);
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

        }



        Log::info("StartCommand: Foydalanuvchi #{$user->id} (@{$username}) ro'yxatdan o'tdi.");
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
        return true;
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