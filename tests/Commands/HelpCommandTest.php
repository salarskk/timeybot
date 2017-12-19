<?php declare(strict_types = 1);
namespace Timey\Test;

require_once __DIR__ .'/../TestHelpers.php';

class HelpCommandTest extends TelegramBotCommandTest
{

    /**
     * @dataProvider helpCommandProvider
     */
    public function testHelpCommand(array $input, string $expected)
    {
        $input = json_encode(self::$helper->fillPrivateMessage($input));
        $this->assertMessageTextContains($input, $expected);
    }

    /**
     * Dataprovider for testHelpCommand.
     */
    public function helpCommandProvider() : array
    {
        return [
            [['text' => '/help'], '/time']
        ];
    }
}
