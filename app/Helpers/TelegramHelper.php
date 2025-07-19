<?php

namespace App\Helpers;

class TelegramHelper
{
    public static function escapeMarkdownV2(string $text): string
    {
        $escape_chars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        foreach ($escape_chars as $char) {
            $text = str_replace($char, '\\' . $char, $text);
        }
        return $text;
    }
}
