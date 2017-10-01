<?php


namespace Longman\TelegramBot\Commands\UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Request;

class HelpCommand extends UserCommand {
    protected $name = 'help';
    protected $description = 'Help command';
    protected $usage = '/help';
    protected $version = '0.5.0';

    public function execute() {
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();

        $data = [
            'chat_id' => $chat_id,
            'text' => 'Hello, I am timey!',
        ];

        return Request::sendMessage($data);
    }
}
