<?php declare(strict_types=1);
namespace Timey\Test;

require_once __DIR__ .'/../TestHelpers.php';

class TimeCommandTest extends TelegramBotCommandTest
{
    /**
     * @dataProvider timeCommandProvider
     */
    public function testTimeCommand(array $input, string $expected)
    {
        $input = json_encode(self::$helper->fillPrivateMessage($input));
        $this->assertMessageTextContains($input, $expected);
    }

    /**
     * Dataprovider for testTimeCommand.
     */
    public function timeCommandProvider() : array
    {
        return [
            [['text' => '/time new york'], 'time...'],
            [['text' => '/time tokyo'], 'time...'],
            [['text' => '/time osnabrück'], 'time...'],
            [['text' => '/time osnabruck'], 'time...'],
            [['text' => '/time 16:00 tomorrow in new york'], 'time...'],
            [['text' => '/time tomorrow 16:00 in new york'], 'time...'],
            [['text' => '/time 16:00 tomorrow in tokyo'], 'time...'],
            [['text' => '/time tomorrow 16:00 in tokyo'], 'time...'],
        ];
    }
}
