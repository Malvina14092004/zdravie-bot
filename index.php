<?php
$botToken = getenv('BOT_TOKEN');
$openRouterKey = getenv('OPENROUTER_KEY');
$model = 'openai/gpt-3.5-turbo-16k';

// =============== ПОЛУЧАЕМ ВХОДЯЩИЙ ЗАПРОС ==============

$input = file_get_contents('php://input');

$update = json_decode($input, true);

if (!isset($update['message'])) {
    exit;
}

$chat_id = $update['message']['chat']['id'];
$text = $update['message']['text'] ?? '';

if ($text === '/start') {
    $reply = "Привет! Я бот «Здоровье под рукой» 🩺\n\nЯ могу ответить на любые твои вопросы о здоровье (справочного характера). Спрашивай!";
} else {
    $reply = askGpt($text);
}

sendMessage($chat_id, $reply);


// =============== ФУНКЦИЯ ОТПРАВКИ В ТЕЛЕГРАМ ==============

function sendMessage($chat_id, $text)
{
    global $botToken;

    $url = "https://api.telegram.org/bot$botToken/sendMessage";

    $data = [
        'chat_id' => $chat_id,
        'text' => $text
    ];

    file_get_contents($url . '?' . http_build_query($data));
}


// =============== ФУНКЦИЯ GPT ==============

function askGpt($userMessage)
{
    global $openRouterKey, $model;

    $url = 'https://openrouter.ai/api/v1/chat/completions';
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openRouterKey
    ];

    $postData = json_encode([
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Ты справочный бот по вопросам здоровья. Давай советы, но обязательно пиши, что твои ответы не заменяют врача. Будь вежлив и дружелюбен.'
            ],
            [
                'role' => 'user',
                'content' => $userMessage
            ]
        ],
        'temperature' => 0.7
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return "Произошла ошибка при обращении к ИИ: $error_msg";
    }

    curl_close($ch);

    $result = json_decode($response, true);

    if (isset($result['error'])) {
        return "Ошибка от OpenRouter: " . $result['error']['message'];
    }

    return $result['choices'][0]['message']['content'] ?? 'Извините, не получилось получить ответ 😔';
}