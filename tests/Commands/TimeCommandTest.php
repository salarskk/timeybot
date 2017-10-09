<?php declare(strict_types=1);

namespace Timey\Test;

use Carbon\Carbon;

require_once __DIR__ .'/../../vendor/autoload.php';
require_once __DIR__ .'/../TestHelpers.php';


class TimeCommandTest extends TelegramBotCommandTest
{
    protected static function getTestDate() : Carbon
    {

        return Carbon::create(2017, 8, 21, 12, 43, 12, 'Europe/Berlin');
    }

    protected static function format(Carbon $carbon) : string
    {
        return $carbon->format(Carbon::COOKIE);
    }

    /**
     * @dataProvider timeCommandProvider
     */
    public function testTimeCommand(string $input, string $expected)
    {
        date_default_timezone_set('Europe/Berlin');
        Carbon::setTestNow(self::getTestDate());

        $input = json_encode(self::$helper->fillPrivateMessage(['text' => "/time {$input}"]));
        $this->assertMessageTextContains($input, $expected);

        Carbon::setTestNow();
    }

    /**
     * Dataprovider for testTimeCommand.
     */
    public function timeCommandProvider() : array
    {
        return [
            [
                'tomorrow 16:00 in new york',
                self::format(self::getTestDate()->addDay(1)
                                                ->hour(16)
                                                ->minute(0)
                                                ->second(0)
                                                ->timezone('America/New_York'))
            ],
            [
                'yesterday 9:00 in tokyo',
                self::format(self::getTestDate()->subDay(1)
                                                ->hour(9)
                                                ->minute(0)
                                                ->second(0)
                                                ->timezone('Asia/Tokyo'))
            ],
            [
                'new york',
                self::format(self::getTestDate()->timezone('America/New_York'))
            ],
            [
                'tokyo',
                self::format(self::getTestDate()->timezone('Asia/Tokyo'))
            ],
            [
                'osnabrück',
                self::format(self::getTestDate()->timezone('Europe/Berlin'))
            ],
        ];
    }
}
