<?php

namespace App\Http\Controllers\Guest;

use App\Services\TelegramService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Utils\Helper;
use App\Models\Plan;
use App\Models\InviteCode;
use App\Models\Order;
use App\Services\TicketService;
use App\Http\Controllers\User\UserController;

class TelegramController extends Controller
{
    protected $msg;

    public function __construct(Request $request)
    {
        if ($request->input('access_token') !== md5(config('v2board.telegram_bot_token'))) {
            abort(500, 'authentication failed');
        }
    }

    public function webhook(Request $request)
    {
        $this->msg = $this->getMessage($request->input());
        if (!$this->msg) return;
        try {
            switch($this->msg->message_type) {
                case 'send':
                    $this->fromSend();
                    break;
                case 'reply':
                    $this->fromReply();
                    break;
            }
        } catch (\Exception $e) {
            $telegramService = new TelegramService();
            $telegramService->sendMessage($this->msg->chat_id, $e->getMessage());
        }
    }

    private function fromSend()
    {
        switch($this->msg->command) {
            case '/bind': $this->bind();
                break;
            case '/traffic': $this->traffic();
                break;
            case '/getlatesturl': $this->getLatestUrl();
                break;
            case '/unbind': $this->unbind();
                break;
            case '/sublink': $this->sublink();
                break;
            case '/start': $this->start();
                break;
            case '/mysubscribe': $this->mysubscribe();
                break;
            case '/mywallet': $this->mywallet();
                break; 
            case '/invite': $this->invite();
                break; 
            default: $this->help();
        }
    }

    private function fromReply()
    {
        // ticket
        if (preg_match("/[#](.*)/", $this->msg->reply_text, $match)) {
            $this->replayTicket($match[1]);
        }
    }

    private function getMessage(array $data)
    {
        $obj = new \StdClass();
        if (!isset($data['message']['text']) and !isset($data['callback_query']) )return false;
        if(isset($data['callback_query'])){
        $obj->command =$data['callback_query']['data'];
        $obj->callback_query_id =$data['callback_query']['id'];
        $obj->chat_id = $data['callback_query']['message']['chat']['id'];
        $obj->user_id = $data['callback_query']['from']['id'];
        $obj->message_id = $data['callback_query']['message']['message_id'];
        $obj->text = $data['callback_query']['message']['text'];
        $obj->message_type = 'send';
        $obj->is_private = $data['callback_query']['message']['chat']['type'] === 'private' ? true : false;
        }else{
        $obj->is_private = $data['message']['chat']['type'] === 'private' ? true : false;
        $text = explode(' ', $data['message']['text']);
        $obj->command = $text[0];
        $obj->args = array_slice($text, 1);
        $obj->chat_id = $data['message']['chat']['id'];
        $obj->message_id = $data['message']['message_id'];
        $obj->user_id = $data['message']['from']['id'];
        $obj->message_type = !isset($data['message']['reply_to_message']['text']) ? 'send' : 'reply';
        $obj->text = $data['message']['text'];
        if ($obj->message_type === 'reply') {
            $obj->reply_text = $data['message']['reply_to_message']['text'];
        }
        }
        
        
        return $obj;
    }
    
    private function start()
    {
        $msg = $this->msg;
        if (!$msg->is_private) return;
        $user = User::where('telegram_id', $msg->user_id)->first();
        $telegramService = new TelegramService();
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
                                    ['text' => "💻教程及客户端下载", 'url' => 'https://t.me/airportcenter'],
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
        if(isset($msg->callback_query_id)){
        $telegramService->editMessageText($msg->chat_id,$msg->message_id, $text, $reply_markup);     
        }else{
        $telegramService->sendMessageMarkup($msg->chat_id, $text, $reply_markup);
        }
    }
    private function mywallet()
    {
        $msg = $this->msg;
        if (!$msg->is_private) return;
        $user = User::where('telegram_id', $msg->user_id)->first();
        $telegramService = new TelegramService();
        if (!$user) {
            $telegramService->answerCallbackQuery($msg->callback_query_id,'没有查询到您的用户信息，请先绑定账号');
            return;
        }
        $commission_balance = $user->commission_balance / 100 ;
        $balance = $user->balance / 100 ;
        $total = $commission_balance + $balance ;
        $text = "我的钱包\n————————————\n钱包总额：$total 元\n账户余额：$balance 元\n推广佣金：$commission_balance 元";

      $telegramService->answerCallbackQuery($msg->callback_query_id, $text);
    }
    private function invite()
    {
        $msg = $this->msg;
        if (!$msg->is_private) return;
        $user = User::where('telegram_id', $msg->user_id)->first();
        $telegramService = new TelegramService();
        if (!$user) {
            $telegramService->answerCallbackQuery($msg->callback_query_id,'没有查询到您的用户信息，请先绑定账号');
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
        $inviteusers = User::where('invite_user_id', $msg->user_id)->count();
        //有效的佣金
        $active_commission = Order::where('status', 3)
                ->where('commission_status', 2)
                ->where('invite_user_id', $msg->user_id)
                ->sum('commission_balance') / 100;
        //确认中的佣金
        $process_commisson = Order::where('status', 3)
                ->where('commission_status', 0)
                ->where('invite_user_id', $msg->user_id)
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
        $text2 = "您的推广链接： \n————————————\n$invite_url";
        $reply_markup =  json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "返回菜单", 'callback_data' => '/start'],
                ]
            ]
        ]); 
        $telegramService->editMessageText($msg->chat_id,$msg->message_id, $text2, $reply_markup); 
        $telegramService->answerCallbackQuery($msg->callback_query_id, $text);
    }
    private function mysubscribe()
    {
        $msg = $this->msg;
        if (!$msg->is_private) return;
        $user = User::where('telegram_id', $msg->user_id)->first();
        $telegramService = new TelegramService();
        if (!$user) {
            $telegramService->answerCallbackQuery($msg->callback_query_id,'没有查询到您的用户信息，请先绑定账号');
            return;
        }
        
        $userplan = Plan::find($user->plan_id);
        if (!$userplan) {
        $telegramService->answerCallbackQuery($msg->callback_query_id,'您暂无订阅');
        return;
        }
        $plan_transfer = $userplan->transfer_enable ;
        $plan_name = $userplan->name ;
        $UserController = new UserController();
        $reset_day = $UserController->getResetDay($user);
        $expired_at = date("Y-m-d",$user->expired_at);
        $text = "我的订阅\n————————————\n套餐名称：$plan_name\n套餐流量：$plan_transfer GB\n离重置流量还有：$reset_day 天\n到期时间：$expired_at";
        $telegramService->answerCallbackQuery($msg->callback_query_id, $text);
      
    }
    private function sublink()
    {
        $msg = $this->msg;
        if (!$msg->is_private) return;
        $user = User::where('telegram_id', $msg->user_id)->first();
        $telegramService = new TelegramService();
        if (!$user) {
            $telegramService->answerCallbackQuery($msg->callback_query_id,'没有查询到您的用户信息，请先绑定账号');
            return;
        }
        
        $userplan = Plan::find($user->plan_id);
        if (!$userplan) {
        $telegramService->answerCallbackQuery($msg->callback_query_id,'您暂无订阅');
        return;
        }
        $subscribe_url = Helper::getSubscribeHost() . "/api/v1/client/subscribe?token={$user['token']}";
        $text = "我的订阅链接：\n————————————\n$subscribe_url";
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
        $telegramService->editMessageText($msg->chat_id,$msg->message_id, $text, $reply_markup); 
    }
    private function bind()
    {
        $msg = $this->msg;
        if (!$msg->is_private) return;
        if (!isset($msg->args[0])) {
            abort(500, '请携带订阅地址发送 /bind 订阅链接');
        }
        $subscribeUrl = $msg->args[0];
        $subscribeUrl = parse_url($subscribeUrl);
        parse_str($subscribeUrl['query'], $query);
        $token = $query['token'];
        if (!$token) {
            abort(500, '订阅地址无效');
        }
        $user = User::where('token', $token)->first();
        if (!$user) {
            abort(500, '用户不存在');
        }
        if ($user->telegram_id) {
            abort(500, '该账号已经绑定了Telegram账号');
        }
        $user->telegram_id = $msg->chat_id;
        if (!$user->save()) {
            abort(500, '设置失败');
        }
        $telegramService = new TelegramService();
        $telegramService->sendMessage($msg->chat_id, '绑定成功');
        $this->start();
    }

    private function unbind()
    {
        $msg = $this->msg;
        if (!$msg->is_private) return;
        $user = User::where('telegram_id', $msg->chat_id)->first();
        $telegramService = new TelegramService();
        if (!$user) {
            $this->help();
            $telegramService->sendMessage($msg->chat_id, '没有查询到您的用户信息，请先绑定账号', 'markdown');
            return;
        }
        $user->telegram_id = NULL;
        if (!$user->save()) {
            abort(500, '解绑失败');
        }
        $telegramService->sendMessage($msg->chat_id, '解绑成功', 'markdown');
    }

    private function help()
    {
        $msg = $this->msg;
        if (!$msg->is_private) return;
        $telegramService = new TelegramService();
        $commands = [
            '/bind 订阅地址 - 绑定你的' . config('v2board.app_name', 'V2Board') . '账号'
        ];
        $text = implode(PHP_EOL, $commands);
        $telegramService->sendMessage($msg->chat_id, "你可以使用以下命令进行操作：\n\n$text", 'markdown');
    }

    private function traffic()
    {
        $msg = $this->msg;
        if (!$msg->is_private) return;
        $user = User::where('telegram_id', $msg->user_id)->first();
        $telegramService = new TelegramService();
        if (!$user) {
            $this->help();
            $telegramService->sendMessage($msg->chat_id, '没有查询到您的用户信息，请先绑定账号', 'markdown');
            return;
        }
        $transferEnable = Helper::trafficConvert($user->transfer_enable);
        $up = Helper::trafficConvert($user->u);
        $down = Helper::trafficConvert($user->d);
        $remaining = Helper::trafficConvert($user->transfer_enable - ($user->u + $user->d));
        $text = "我的流量\n————————————\n计划流量：{$transferEnable}\n已用上行：{$up}\n已用下行：{$down}\n剩余流量：{$remaining}";
        $telegramService->answerCallbackQuery($msg->callback_query_id, $text);
    }

    private function getLatestUrl()
    {
        $msg = $this->msg;
        $user = User::where('telegram_id', $msg->chat_id)->first();
        $telegramService = new TelegramService();
        $text = sprintf(
            "%s的最新网址是：%s",
            config('v2board.app_name', 'V2Board'),
            config('v2board.app_url')
        );
        $telegramService->sendMessage($msg->chat_id, $text, 'markdown');
    }

    private function replayTicket($ticketId)
    {
        $msg = $this->msg;
        if (!$msg->is_private) return;
        $user = User::where('telegram_id', $msg->chat_id)->first();
        if (!$user) {
            abort(500, '用户不存在');
        }
        $ticketService = new TicketService();
        if ($user->is_admin || $user->is_staff) {
            $ticketService->replyByAdmin(
                $ticketId,
                $msg->text,
                $user->id
            );
        }
        $telegramService = new TelegramService();
        $telegramService->sendMessage($msg->chat_id, "#`{$ticketId}` 的工单已回复成功", 'markdown');
        $telegramService->sendMessageWithAdmin("#`{$ticketId}` 的工单已由 {$user->email} 进行回复", true);
    }


}
