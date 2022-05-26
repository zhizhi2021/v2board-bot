<?php

namespace App\Plugins\Telegram\Commands;

use App\Models\User;
use App\Plugins\Telegram\Telegram;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class Start extends Telegram {
    public $command = '/start';
    public $description = '开始菜单';

    public function handle($message, $match = []) {
        $telegramService = $this->telegramService;
        if (!$message->is_private) return;
        $user = User::where('telegram_id', $message->user_id)->first();
        $app_url = sprintf(
            config('v2board.app_url')
        );
        if($user){
        $reply_markup =  json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => "💰我的钱包", 'callback_data' => '/mywallet'], ['text' => "🎫流量查询", 'callback_data' => '/traffic']
                                ],
                                [
                                    ['text' => "📖订阅链接", 'callback_data' => '/sublink'],['text' => "📝我的订阅", 'callback_data' => '/mysubscribe']
                                ],
                                [
                                    ['text' => "🏠购买套餐", 'callback_data' => '/store'],
                                 ],
                                [
                                    ['text' => "💲邀请返利", 'callback_data' => '/invite'],['text' => "💁最新官网", 'url' => $app_url]
                                ]
                            ]
                        ]);
        }else{
        $reply_markup =  json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => "注册账户", 'url' => $app_url],
                        ],
                        [
                            ['text' => "绑定账户", 'callback_data' => '/bind'],
                        ]
                    ]
                ]); 
        }
       $text = sprintf(
            "尊敬的用户，欢迎使用 %s\n%s",
            config('v2board.app_name', 'V2Board'),
            config('v2board.app_description')
        );
        if(isset($message->callback_query_id)){
        $telegramService->editMessageText($message->chat_id,$message->message_id, $text, $reply_markup);     
        }else{
        $telegramService->sendMessageMarkup($message->chat_id, $text, $reply_markup);
        }
    }
}
