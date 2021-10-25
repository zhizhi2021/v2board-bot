<?php
namespace App\Services;

use App\Jobs\SendTelegramJob;
use App\Models\User;
use \Curl\Curl;

class TelegramService {
    protected $api;

    public function __construct($token = '')
    {
        $this->api = 'https://api.telegram.org/bot' . config('v2board.telegram_bot_token', $token) . '/';
    }

    public function sendMessageMarkup(int $chatId, string $text, string $reply_markup)
    {
        $this->request('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => $reply_markup
        ]);
    }
    public function answerCallbackQuery(int $callback_query_id, string $text, $show_alert = true)
    {
        $this->request('answerCallbackQuery', [
            'callback_query_id' => $callback_query_id,
            'text' => $text,
            'show_alert' => $show_alert
        ]);
    }
    public function editMessageText(int $chatId, string $message_id, string $text, string $reply_markup)
    {
        $this->request('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $message_id,
            'text' => $text,
            'reply_markup' => $reply_markup
        ]);
    }
    public function sendMessage(int $chatId, string $text, string $parseMode = '')
    {
        $this->request('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode
        ]);
    }

    public function getMe()
    {
        return $this->request('getMe');
    }

    public function setWebhook(string $url)
    {
        return $this->request('setWebhook', [
            'url' => $url
        ]);
    }
   
    private function request(string $method, array $params = [])
    {
        $curl = new Curl();
        $curl->get($this->api . $method . '?' . http_build_query($params));
        $response = $curl->response;
        $curl->close();
        if (!isset($response->ok)) abort(500, '请求失败');
        if (!$response->ok) {
            abort(500, '来自TG的错误：' . $response->description);
        }
        return $response;
    }

    public function sendMessageWithAdmin($message, $isStaff = false)
    {
        if (!config('v2board.telegram_bot_enable', 0)) return;
        $users = User::where(function ($query) use ($isStaff) {
            $query->where('is_admin', 1);
            if ($isStaff) {
                $query->orWhere('is_staff', 1);
            }
        })
            ->where('telegram_id', '!=', NULL)
            ->get();
        foreach ($users as $user) {
            SendTelegramJob::dispatch($user->telegram_id, $message);
        }
    }
}
