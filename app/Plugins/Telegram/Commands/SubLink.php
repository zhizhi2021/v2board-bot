<?php

namespace App\Plugins\Telegram\Commands;

use App\Models\User;
use App\Plugins\Telegram\Telegram;
use App\Models\Plan;
use App\Utils\Helper;

class SubLink extends Telegram {
    public $command = '/sublink';
    public $description = '查询流量';
    public function handle($message, $match = []) {
        $telegramService = $this->telegramService;
        if (!$message->is_private) return;
        $user = User::where('telegram_id', $message->user_id)->first();
        if (!$user) {
            $telegramService->answerCallbackQuery($message->callback_query_id,'没有查询到您的用户信息，请先绑定账号','true');
            return;
        }
        $userplan = Plan::find($user->plan_id);
        if (!$userplan) {
        $telegramService->answerCallbackQuery($message->callback_query_id,'您暂无订阅','true');
        return;
        }
        $subscribe_url = Helper::getSubscribeHost() . "/api/v1/client/subscribe?token={$user['token']}";
        $text = "我的订阅链接(点击即可复制)：\n————————————\n`$subscribe_url`";
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
        $telegramService->editMessageText($message->chat_id,$message->message_id, $text, $reply_markup); 
    }
}
