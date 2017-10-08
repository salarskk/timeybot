<?php declare(strict_types = 1);
namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\Command;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\ServerResponse;
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

    protected function parse_message(string $message) : string
    {
        // TODO(shoeffner): Implement proper date parsing
        return $message;
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
            'text' => 'time...' . PHP_EOL . $parsed_message,
        ];

        return Request::sendMessage($data);
    }
}
