<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TextSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('texts')->updateOrInsert(
            ['key' => 'subscription_success'],
            ['text' => <<<EOT
🎉 *Marafonda ishtirok eting va sovg'alarga ega bo'ling\!*

📨 *Shaxsiy taklif havolangiz:*
{referral_link}

👥 Ushbu havolani do'stlaringizga yuboring, ball to'plang va reytingda yuqoriga chiqing\!
Faol qatnashing \- eng ko'p foydalanuvchi taklif qilgan ishtirokchilar *qimmatbaho mukofotlar* bilan taqdirlanadi\!

🎁 *Sovg'alar ro'yxati:*
🥇 *1\-o'rin:* "Eng yaxshi izlanuvchi" ko'krak nishoni 🎖
🥈 *2\-o'rin:* "Professional lider" nishoni 🇺🇿🇹🇷
🥉 *3\-o'rin:* Yevropa konferensiyasida *bepul maqola chop etish* imkoniyati 📑
🏅 *4\-o'rin:* Xalqaro darajadagi sertifikatlar 📯
🏅 *5\-o'rin:* Xalqaro darajadagi sertifikatlar 📯

🔔 Do'stlaringiz qancha ko'p bo'lsa \- imkoniyat shuncha yuqori\!
EOT




            ]
        );
    }
}
