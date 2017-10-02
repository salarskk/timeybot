<?php declare(strict_types=1);
namespace Timey\Test;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../manager.php';

use \DateTime;

use \TelegramBot\TelegramBotManager\BotManager;

use function \Timey\createBotManager;


class TestHelper
{
    public $config;
    public $botManager;

    public function __construct()
    {
        $this->botManager = createBotManager(__DIR__ . '/config.test.json');
    }

    /**
     * Fills a private message with default values.
     */
    public function fillPrivateMessage(array $message_stub) : array
    {
        $dateTime = new DateTime();
        $message = [
            'update_id' => 1,
            'message' => [
                'message_id' => 1,
                'from' => [
                    'id' => 1,
                    'is_bot' => false,
                    'first_name' => 'Testy',
                    'last_name' => 'Tester',
                    'username' => 'ttester',
                    'language_code' => 'en-EN',
                ],
                'chat' => [
                    'id' => 1,
                    'first_name' => 'Testy',
                    'last_name' => 'Tester',
                    'username' => 'ttester',
                    'type' => 'private'
                ],
                'date' => $dateTime->getTimestamp(),
                'text' => '/help',
                'entities' => [
                    [
                        'offset' => 0,
                        'length' => 0,
                        'type' => 'bot_command'
                    ]
                ]
            ]
        ];
        $message['message'] = array_merge($message['message'], $message_stub);
        foreach ($message['message']['entities'] as $i => $entity) {
            if (array_key_exists('type', $entity)
                    && $entity['type'] == 'bot_command') {
                $message['message']['entities'][$i]['length'] =
                    strlen($message['message']['text']);
            }
        }
        return $message;
    }
}

class TelegramBotCommandTest extends \PHPUnit\Framework\TestCase
{

    protected static $helper;

    public static function setUpBeforeClass()
    {
        self::$helper = new TestHelper();
    }

    protected function getMessageResponse(string $input_msg) :
        \Longman\TelegramBot\Entities\ServerResponse
    {
        self::$helper->botManager->getTelegram()->setCustomInput($input_msg);
        self::$helper->botManager->run();
        return self::$helper->botManager
                            ->getTelegram()
                            ->getLastCommandResponse();
    }

    protected function assertMessageContains(string $input, string $expected)
    {
        $response = json_encode($this->getMessageResponse($input));
        self::assertContains($expected, $response);
    }

    protected function assertMessageTextContains(string $input, string $expected)
    {
        // TODO(shoeffner): There must be an easier way to this :D
        $response_text = json_decode(
            json_encode($this->getMessageResponse($input)),
            true)['result']['text'];
        self::assertContains($expected, $response_text);
    }

}
