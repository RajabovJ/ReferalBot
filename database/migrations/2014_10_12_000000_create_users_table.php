<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
                $table->id(); // Avtomatik primary key
                $table->unsignedBigInteger('telegram_id')->unique(); // Telegram foydalanuvchi ID
                $table->string('username')->nullable(); // Telegram @username
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();

                $table->unsignedBigInteger('referrer_id')->nullable(); // Kim orqali kelgan
                $table->boolean('is_admin')->default(false); // Admin yoki oddiy user
                $table->boolean('referral_verified')->default(false); // A’zolik tasdiqlangandan keyin true bo'ladi
                $table->boolean('got_invite_link')->default(false); // Guruhga taklif havolasi berildimi
                $table->string('step')->nullable();
                $table->timestamps();

                // Referalga tegishli bo‘lgan foreign key
                $table->foreign('referrer_id')
                      ->references('id')
                      ->on('users')
                      ->nullOnDelete();

        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
