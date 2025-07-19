<?php
namespace App\Services\Telegram\Text;

use App\Models\Text as ModelsText;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\Update;

class AdminTextMessageHandler
{
    public function handle(Update $update, User $user)
    {
        $botUsername = env('TELEGRAM_BOT_USERNAME', 'YourBotUsername');
        $text = $update->getMessage()->getText();
        $chatId = $user->telegram_id;
        if ($user->step === 'post_yuborish') {
            // step ni bekor qilamiz
            $user->step = null;
            $user->save();
            $msgId = Cache::get("post_message_id_$chatId");
            Telegram::deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $msgId,
            ]);
            Cache::forget("post_message_id_$chatId");
            $update = Telegram::getWebhookUpdate();
            $message = $update->getMessage();

            $users = User::whereNotNull('telegram_id')->pluck('telegram_id');

            foreach ($users as $toChatId) {
                try {
                    Telegram::copyMessage([
                        'chat_id' => $toChatId,
                        'from_chat_id' => $chatId,
                        'message_id' => $message->getMessageId(),
                    ]);
                } catch (\Exception $e) {
                    // xatolarni logga yozish yoki tashlab ketish
                }
            }
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
                'text' => "✅ Post muvaffaqiyatli yuborildi!",
                'reply_markup' => json_encode([
                                    'keyboard' => $keyboard,
                                    'resize_keyboard' => true,
                                    'one_time_keyboard' => false,
                                ]),
            ]);

            return;
        }

        switch ($text) {
            case '💎 Do‘stlarni taklif qilish':
                $referralLink = "https://t\\.me/{$botUsername}?start\\={$chatId}";
                $template = ModelsText::where('key', 'subscription_success')->value('text') ?? '';
                // Matn shabloni
                $template = ModelsText::where('key', 'subscription_success')->value('text') ?? '';
                $finalText = str_replace(
                    ['{referral_link}'],
                    [$referralLink],
                    $template
                );
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
                break;
                case '📣 Mening do‘stlarim':
                    $fullName = $user->first_name . ' ' . ($user->last_name ?? '');
                    $refCount = $user->referrals()->where('referral_verified', true)->count();
                    $refLink = "https://t.me/{$botUsername}?start={$chatId}";
                    // MarkdownV2 formatida maxsus belgilarni qochirish
                    $safeName = str_replace(['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'], array_map(fn($c) => '\\' . $c, ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!']), $fullName);
                    $message = "👋 Hurmatli *{$safeName}*\\!\n\n";
                    $message .= "Sizning do‘stlaringiz soni: *{$refCount}* ta\\.\n";
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => $message,
                        'parse_mode' => 'MarkdownV2',
                        'disable_web_page_preview' => true
                    ]);
                    break;
                case '📊 Natijalar':
                    $leaders = User::whereHas('referrals', function ($query) {
                        $query->where('referral_verified', true);
                    })
                    ->withCount([
                        'referrals as verified_referrals_count' => function ($query) {
                            $query->where('referral_verified', true);
                        }
                    ])
                    ->orderByDesc('verified_referrals_count')
                    ->take(10)
                    ->get();

                    if ($leaders->isEmpty()) {
                        $message = "📈 Hali hech kim referal orqali foydalanuvchi taklif qilmagan.";
                    } else {
                        $message = "🏆 <b>Eng faol 10 ta foydalanuvchi:</b>\n\n";
                        foreach ($leaders as $index => $user) {
                            $username = $user->username ? '@' . $user->username : ($user->first_name ?? '👤 Nomaʼlum foydalanuvchi');
                            $count = $user->verified_referrals_count ?? 0;
                            $message .= ($index + 1) . ". $username — <b>$count ta</b> taklif\n";
                        }
                    }

                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => $message,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => '🔍 Hamma natijalarni ko‘rish',
                                        'web_app' => [
                                            'url' => 'https://www.php.net/manual/en/language.types.string.php#language.types.string.syntax.heredoc' // ← bu yerga o‘z sahifangizni kiriting
                                        ]
                                    ]
                                ]
                            ]
                        ]),
                    ]);
                    break;

            case '📝 Tanlov shartlari':
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => <<<EOT
            📋 *Tanlovda ishtirok etish shartlari:*
            1️⃣ Shaxsiy taklif havolangizni do‘stlaringizga yuboring.
            2️⃣ Har bir ro‘yxatdan o‘tgan va ishtirokni tasdiqlagan do‘stingiz sizga ball olib keladi.
            3️⃣ Ballar soniga ko‘ra *eng faol ishtirokchilar* g‘olib sifatida e’lon qilinadi.
            🏆 G‘oliblar *ochiq va shaffof reyting asosida* belgilanadi.
            🎁 *Sovrinli o‘rinlar va rag‘batlantiruvchi mukofotlar* sizni kutmoqda!
            ⏳ *Shoshiling!* Qancha erta boshlasangiz, shuncha ko‘p do‘st taklif qilib ball to‘plash imkoniyati bo‘ladi.
            EOT,
                    'parse_mode' => 'Markdown'
                ]);
                break;


            case '🎁 Sovg‘alar':
                $giftMessage = "🎉 <b>Tanlov sovg‘alari:</b>\n\n" .
                    "🥇 <b>1-o‘rin:</b> \"<i>Eng yaxshi izlanuvchi</i>\" koʻkrak nishoni 🎖\n" .
                    "🥈 <b>2-o‘rin:</b> \"<i>Professional lider</i>\" nishoni 🇹🇷🇺🇿 🎖\n" .
                    "🥉 <b>3-o‘rin:</b> 1 ta maqolani Yevropa konferensiyasiga <b>tekin chop etish imkoni</b> 📑\n" .
                    "🏅 <b>4–5-o‘rinlar:</b> <i>Xalqaro tan olingan sertifikatlar</i> 📯\n\n" .
                    "🎊 Shuningdek, barcha faol ishtirokchilar uchun qo‘shimcha rag‘batlantiruvchi sovg‘alar ham taqdim etiladi!\n\n" .
                    "🧩 Tanlovda qatnashish uchun referal havolangizni ulashing va faol bo‘ling!";
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => $giftMessage,
                    'parse_mode' => 'HTML',
                ]);
                break;


            case '📬 Post yaratish':
                // step saqlab qo'yamiz
                $user->step = 'post_yuborish';
                $user->save();
                // 1. Avval reply tugmalarni o‘chirib yuboramiz
                $responsereply=Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "⏳...",
                    'reply_markup' => json_encode([
                        'remove_keyboard' => true
                    ]),
                ]);
                // 2. So‘ng inline tugma bilan yangi post xabari yuboriladi
                $response = Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "✍️ Yubormoqchi bo‘lgan post matnini (yoki rasm, video bilan birga) yuboring.\n\nBekor qilish uchun quyidagi tugmani bosing:",
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '❌ Bekor qilish', 'callback_data' => 'cancel_post']
                            ]
                        ]
                    ]),
                ]);
                $msgId=$responsereply['message_id'];
                Telegram::deleteMessage([
                    'chat_id' => $chatId,
                    'message_id' => $msgId,
                ]);
                Cache::forever("post_message_id_$chatId", $response['message_id']);
                break;

            default:
            if($text!='/start')
            {
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
                    'text' => '👇 Quyidagi tugmalardan foydalaning:',
                    'reply_markup' => json_encode([
                        'keyboard' => $keyboard,
                        'resize_keyboard' => true,
                        'one_time_keyboard' => false,
                    ]),
                ]);
            }
                break;
        }



    }
}
