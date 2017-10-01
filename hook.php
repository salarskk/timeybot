<?php
require __DIR__ . '/vendor/autoload.php';


$config = json_decode(file_get_contents('config.json'), TRUE);


$commands_paths = [
    __DIR__ . '/Commands/',
];

try {
    $telegram = new Longman\TelegramBot\Telegram(
        $config['bot']['api_key'],
        $config['bot']['username']);

    $telegram->addCommandsPaths($commands_paths);

    Longman\TelegramBot\TelegramLog::initErrorLog(__DIR__ . "/{$config['bot']['username']}_error.log");
    Longman\TelegramBot\TelegramLog::initDebugLog(__DIR__ . "/{$config['bot']['username']}_debug.log");
    Longman\TelegramBot\TelegramLog::initUpdateLog(__DIR__ . "/{$config['bot']['username']}_update.log");

    $telegram->enableLimiter();

    $telegram->handle();
} catch (Longman\TelegramBot\Exception\TelegramException $e) {
    Longman\TelegramBot\TelegramLog::error($e);
} catch (Longman\TelegramBot\Exception\TelegramLogException $e) {
    file_put_contents('log.log', $e);
    die($e);
}
