<?php

namespace App\Plugins\Telegram\Commands;

use App\Models\User;
use App\Plugins\Telegram\Telegram;
use App\Utils\Helper;
use App\Models\InviteCode;
use App\Models\Order;

class Invite extends Telegram {
    public $command = '/invite';
    public $description = '查询流量';
    public function handle($message, $match = []) {
        $telegramService = $this->telegramService;
        if (!$message->is_private) return;
        $user = User::where('telegram_id', $message->chat_id)->first();
                if (!$user) {
            $telegramService->answerCallbackQuery($message->callback_query_id,'没有查询到您的用户信息，请先绑定账号','true');
            return;
        }
        $inviteCode = InviteCode::where('user_id', $user->id)
            ->where('status', 0)
            ->first();
        $commission_rate = config('v2board.invite_commission', 10);
        if ($user->commission_rate) {
            $commission_rate = $user->commission_rate;
        }
        //邀请用户数
        $inviteusers = User::where('invite_user_id', $message->user_id)->count();
        //有效的佣金
        $active_commission = Order::where('status', 3)
                ->where('commission_status', 2)
                ->where('invite_user_id', $message->user_id)
                ->sum('commission_balance') / 100;
        //确认中的佣金
        $process_commisson = Order::where('status', 3)
                ->where('commission_status', 0)
                ->where('invite_user_id', $message->user_id)
                ->sum('commission_balance') / 100;
        //可用佣金
        $commission_balance = $user->commission_balance / 100 ;
        //邀请链接
        if(!isset($inviteCode->code)){
        $inviteCode = new InviteCode();
        $inviteCode->user_id = $user->id;
        $inviteCode->code = Helper::randomChar(8);
        $inviteCode->save();
        }
        $invite_url = Helper::getSubscribeHost() . "/register?code={$inviteCode->code}"; 
        $text = "我的邀请\n————————————\n我邀请的人数：$inviteusers 人\n我的返利比例：$commission_rate %\n现有效的佣金：$active_commission 元\n确认中的佣金：$process_commisson 元\n目前可用佣金：$commission_balance 元\n";
        $text2 = "您的推广链接： \n————————————\n`$invite_url`";
        $reply_markup =  json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "返回菜单", 'callback_data' => '/start'],
                ]
            ]
        ]); 
        $telegramService->editMessageText($message->chat_id,$message->message_id, $text2, $reply_markup); 
        $telegramService->answerCallbackQuery($message->callback_query_id, $text);
    
    }
}
