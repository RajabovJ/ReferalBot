<?php

use Illuminate\Support\Facades\Route;
use Telegram\Bot\Laravel\Facades\Telegram;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Route::get('/setwebhook/11111111', function () {
    $response = Telegram::setWebhook([
        'url' => env('TELEGRAM_WEBHOOK_URL')
    ]);

    if (!$response) {
        return response()->json([
            'success' => false,
            'message' => 'Webhook o‘rnatilmadi. Ehtimol, noto‘g‘ri URL yoki Telegram API muammo chiqargan.',
        ], 500);
    }

    return response()->json([
        'success' => true,
        'message' => 'Webhook muvaffaqiyatli o‘rnatildi!',
    ]);
});
