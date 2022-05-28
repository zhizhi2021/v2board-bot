<?php

namespace App\Plugins\Telegram\Commands;

use App\Models\User;
use App\Plugins\Telegram\Telegram;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Http\Controllers\User\UserController;

class MySubscribe extends Telegram {
    public $command = '/mysubscribe';
    public $description = '我的订阅';

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
        $plan_transfer = $userplan->transfer_enable ;
        $plan_name = $userplan->name ;
        $UserController = new UserController();
        $reset_day = $UserController->getResetDay($user);
        $expired_at = date("Y-m-d",$user->expired_at);
        $text = "我的订阅\n————————————\n套餐名称：$plan_name\n套餐流量：$plan_transfer GB\n离重置流量还有：$reset_day 天\n到期时间：$expired_at";
        $reply_markup =  json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "返回菜单", 'callback_data' => '/start'],
                ]
            ]
        ]); 
        $telegramService->editMessageText($message->chat_id,$message->message_id,  $text, $reply_markup);
    }
}
