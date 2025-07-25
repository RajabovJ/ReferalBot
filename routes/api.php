<?php

use App\Http\Controllers\TelegramBotController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
// Route::post('/telegram/webhook', function (Request $request) {
//     Log::info('Telegram webhook keldi:', $request->all());
//     return response('OK');
// });
Route::post('/telegram/webhook',[TelegramBotController::class,'handle']);