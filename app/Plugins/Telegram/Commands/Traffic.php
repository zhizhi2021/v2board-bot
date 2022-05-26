<?php

namespace App\Plugins\Telegram\Commands;

use App\Models\User;
use App\Plugins\Telegram\Telegram;
use App\Utils\Helper;

class Traffic extends Telegram {
    public $command = '/traffic';
    public $description = '查询流量';
    public function handle($message, $match = []) {
        $telegramService = $this->telegramService;
        if (!$message->is_private) return;
        $user = User::where('telegram_id', $message->chat_id)->first();
        if (!$user) {
            $telegramService->sendMessage($message->chat_id, '没有查询到您的用户信息，请先绑定账号', 'markdown');
            return;
        }
        $transferEnable = Helper::trafficConvert($user->transfer_enable);
        $up = Helper::trafficConvert($user->u);
        $down = Helper::trafficConvert($user->d);
        $remaining = Helper::trafficConvert($user->transfer_enable - ($user->u + $user->d));
        $text = "🚥流量查询\n———————————————\n计划流量：`$transferEnable`\n已用上行：`$up`\n已用下行：`$down`\n剩余流量：`$remaining`";
        $reply_markup =  json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "订阅信息", 'callback_data' => '/mysubscribe'],
                ],
                [
                    ['text' => "返回菜单", 'callback_data' => '/start'],
                ]
            ]
        ]); 
        $telegramService->editMessageText($message->chat_id, $message->message_id, $text, $reply_markup); 
    }
}
