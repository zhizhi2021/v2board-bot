<?php

namespace App\Plugins\Telegram\Commands;

use App\Models\User;
use App\Plugins\Telegram\Telegram;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Http\Controllers\User\UserController;

class Kefu extends Telegram {
    public $command = '/kefu';
    public $description = '客服';

    public function handle($message, $match = []) {
        $telegramService = $this->telegramService;
        if (!$message->is_private) return;
        $user = User::where('telegram_id', $message->user_id)->first();
        $userplan = Plan::find($user->plan_id);
        if (!$userplan) {
        $plan_transfer = 0;
        $plan_name = "暂无订阅" ;
        $reset_day = 0;
        }else{
        $plan_transfer = $userplan->transfer_enable ;
        $plan_name = $userplan->name ;
        $UserController = new UserController();
        $reset_day = $UserController->getResetDay($user);
        }
        $expired_at = date("Y-m-d",$user->expired_at);
        $admin = User::where('id', 1)->first();
        $text = "用户信息：\n————————————\nTG账户:$message->user_id\n用户余额：$user->balance\n返利金额：$user->commission_balance\n套餐名称：$plan_name\n套餐流量：$plan_transfer GB\n离重置流量还有：$reset_day 天\n到期时间：$expired_at";
        $telegramService->sendMessage($admin->telegram_id, $text);
        $text = "点击下方按钮联系客服";
        $reply_markup =  json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => "点击联系客服", 'url' => "https://t.me/zhizhizh"]
                        ],
                        [
                            ['text' => "返回菜单", 'callback_data' => '/start'],
                        ]
                    ]
                ]); 
        $telegramService->editMessageText($message->chat_id,$message->message_id,  $text, $reply_markup);
    }
}
