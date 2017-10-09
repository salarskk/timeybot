<?php declare(strict_types = 1);

namespace Longman\TelegramBot\Commands\UserCommands;

use Carbon\Carbon;
use Longman\TelegramBot\Commands\Command;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;

/**
 * User "/time" command.
 */
class TimeCommand extends UserCommand
{
    protected $name = 'time';
    protected $description = 'Converts time.';
    protected $usage = '/time <time string>';
    protected $version = '1.0.0';

    protected function getTimezoneByCity(string $city) : string
    {
        // TODO(shoeffner): Get timezone from database or API.
        switch (strtolower(trim($city))) {
            case 'new york':
                return 'America/New_York';
            case 'tokyo':
                return 'Asia/Tokyo';
            case 'osnabrück':
                return 'Europe/Berlin';
        }
        return 'Europe/Berlin';
    }

    protected function parse_message(string $message) : string
    {
        // TODO(shoeffner): Currently assumes local server time to be the
        //                  user's time. Change this!
        date_default_timezone_set('Europe/Berlin');

        $time_and_city = explode('in', $message);
        $time = $time_and_city[0];

        try {
            // Try parsing date
            $parsed = Carbon::parse($time);
        } catch (\Exception $e) {
            // Assume city name only
            $timezone = $this->getTimezoneByCity($time);
            $parsed = Carbon::now($timezone);
        }

        if (count($time_and_city) > 1) {
            $city = $time_and_city[1];
            $timezone = $this->getTimezoneByCity($city);
            $parsed = $parsed->timezone($timezone);
        }

        return $parsed->format(\DateTime::COOKIE);
    }

    public function execute() : ServerResponse
    {
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();
        $text = trim($message->getText(true));

        if ($message->getReplyToMessage() != null) {
            $reply = $message->getReplyToMessage()->getText();
            $parsed_message = $this->parse_message($reply);
        } else {
            $parsed_message = $this->parse_message($text);
        }

        $data = [
            'chat_id' => $chat_id,
            'text' => $parsed_message,
        ];

        return Request::sendMessage($data);
    }
}
