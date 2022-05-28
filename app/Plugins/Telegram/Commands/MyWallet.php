<?php

namespace App\Plugins\Telegram\Commands;

use App\Models\User;
use App\Plugins\Telegram\Telegram;
use App\Utils\Helper;

class MyWallet extends Telegram {
    public $command = '/mywallet';
    public $description = '查询钱包';
    public function handle($message, $match = []) {
        $telegramService = $this->telegramService;
        if (!$message->is_private) return;
        $user = User::where('telegram_id', $message->chat_id)->first();
        if (!$user) {
            $telegramService->answerCallbackQuery($msg->callback_query_id,'没有查询到您的用户信息，请先绑定账号');
            return;
        }
        $commission_balance = $user->commission_balance / 100 ;
        $balance = $user->balance / 100 ;
        $total = $commission_balance + $balance ;
        $text = "💰我的钱包\n————————————\n钱包总额：$total 元\n账户余额：$balance 元\n推广佣金：$commission_balance 元";
        $reply_markup =  json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "返回菜单", 'callback_data' => '/start'],
                ]
            ]
        ]); 
        $telegramService->editMessageText($message->chat_id,$message->message_id,$text, $reply_markup);
    }
}
