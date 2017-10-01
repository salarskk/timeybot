<?php
// Call this script with ?apikey=YOUR_BOT_API_KEY
require __DIR__ . '/vendor/autoload.php';


$config = json_decode(file_get_contents('config.json'), TRUE);

$bot_api_key  = $config['bot']['api_key'];
$bot_username = $config['bot']['username'];
$hook_url = $config['web']['base_url'] . '/hook.php';

echo $bot_api_key;
echo $bot_username;
if ($_GET['apikey'] != $bot_api_key) {
    die('Sorry, you do not seem to be authorized to do this.');
}

try {
    $telegram = new Longman\TelegramBot\Telegram($bot_api_key, $bot_username);

    // Set webhook
    $result = $telegram->setWebhook($hook_url);
    if ($result->isOk()) {
        echo $result->getDescription();
    }
} catch (Longman\TelegramBot\Exception\TelegramException $e) {
    echo $e->getMessage();
}
