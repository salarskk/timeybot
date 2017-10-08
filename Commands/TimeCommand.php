<?php declare(strict_types = 1);
namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\Command;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Request;

/**
 * User "/time" command
 *
 * Command that lists all available commands and displays them in User and Admin sections.
 */
class TimeCommand extends UserCommand
{
    protected $name = 'time';
    protected $description = 'Converts time.';
    protected $usage = '/time <time string>';
    protected $version = '1.0.0';

    public function execute()
    {
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();
        $text = trim($message->getText(true));
        $data = [
            'chat_id' => $chat_id,
            'text' => 'Some time...' . PHP_EOL . $message->getText(true),
        ];
        return Request::sendMessage($data);
    }
}
