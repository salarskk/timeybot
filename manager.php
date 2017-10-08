<?php declare(strict_types = 1);
namespace Timey;

use \TelegramBot\TelegramBotManager\BotManager;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Reads the config file and creates a BotManager.
 *
 * @param config_file_path The configuration file path.
 * @return A BotManager instance.
 */
function createBotManager(string $config_file_path) : BotManager
{
    $config = json_decode(file_get_contents($config_file_path), TRUE);
    if (defined('PHPUNIT_TESTSUITE')) {
        $merge_config = [];
    } else {
        $merge_config = [
            'logging' => [
                // 'update' => __DIR__ . '/php-telegram-bot-update.log',
                // 'debug'  => __DIR__ . '/php-telegram-bot-debug.log',
                'error'  => __DIR__ . '/php-telegram-bot-error.log',
            ],
            'database' => [
                'host' => $config['database']['host'],
                'user' => $config['database']['user'],
                'password' => $config['database']['password'],
                'database' => $config['database']['database'],
            ]
        ];
    }
    return new BotManager(array_merge([
        'api_key' => $config['bot']['api_key'],
        'bot_username'=> $config['bot']['username'],
        'secret' => $config['web']['app_secret'],

        'webhook' => [
            'url' => "{$config['web']['base_url']}/manager.php",
        ],

        'validate_request' => true,

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
    ], $merge_config));
}


/**
 * Creates and runs a BotManager.
 */
function main()
{
    try {
        $bot = createBotManager(__DIR__ . '/config.json');
        $bot->run();
    } catch (\Exception $e) {
        echo $e->getMessage() . PHP_EOL;
    }
}


if (realpath(__FILE__) ==
        realpath($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'])) {
    main();
}
