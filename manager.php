<?php
use TelegramBot\TelegramBotManager\BotManager;

require_once __DIR__ . '/vendor/autoload.php';


$config = json_decode(file_get_contents('config.json'), TRUE);

try {
    $bot = new BotManager([
        'api_key' => $config['bot']['api_key'],
        'bot_username'=> $config['bot']['username'],
        'secret' => $config['web']['app_secret'],

        'webhook' => [
            'url' => "{$config['web']['base_url']}/manager.php",
        ],

        // 'validate_request' => true,

        'logging' => [
            'update' => __DIR__ . '/php-telegram-bot-update.log',
            'debug'  => __DIR__ . '/php-telegram-bot-debug.log',
            'error'  => __DIR__ . '/php-telegram-bot-error.log',
        ],

        'limiter' => [
            'enabled' => true,
            'options' => [
                'interval' => 0.5,
            ],
        ],

        'admins' => $config['bot']['admins'],

        'paths' => [
            'download' => __DIR__ . '/Download',
            'upload'   => __DIR__ . '/Upload',
        ],

        'commands' => [
            'paths' => [
                __DIR__ . '/Commands',
            ],
            // Command configurations
            'configs' => [
            ],
        ],

        'valid_ips' => $config['web']['valid_ips']
    ]);
    $bot->run();
} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
}
