<?php

namespace App\Plugins\Telegram\Commands;

use App\Models\User;
use App\Plugins\Telegram\Telegram;
use App\Utils\Helper;

class Store extends Telegram {
    public $command = '/store';
    public $description = '购买套餐';
    public function handle($message, $match = []) {
        $telegramService = $this->telegramService;
        if (!$message->is_private) return;
        $telegramService->answerCallbackQuery($message->callback_query_id, '别着急，马上上线了','true');
    }
}
