<?php
#label app/Command/Twitch/DefaultController.php

namespace App\Command\Twitch;

use App\TwitchChatClient;
use Minicli\Command\CommandController;

class DefaultController extends CommandController
{
    public $mods;
    public $vips;
    public $messages;
    public $bots;
    public $countCommands;

    public function __construct()
    {
        $this->mods = require(__DIR__ .'/../../Enums/Mods.php');
        $this->vips = require(__DIR__ .'/../../Enums/Vips.php');
        $this->bots = require(__DIR__ .'/../../Enums/Bots.php');
        $this->messages = require(__DIR__ .'/../../Enums/MessageCommands.php');
        $this->countCommands = require(__DIR__ .'/../../Enums/CountCommands.php');
    }

    public function handle()
    {
        $this->getPrinter()->info("Starting Minichat...");

        $app = $this->getApp();

        $twitch_user = $app->config->twitch_user;
        $twitch_oauth = $app->config->twitch_oauth;
        $twitch_channel = $app->config->twitch_channel;

        if (!$twitch_user or !$twitch_oauth) {
            $this->getPrinter()->error("Missing 'twitch_user' and/or 'twitch_oauth' config settings.");
            return;
        }

        $client = new TwitchChatClient($twitch_user, $twitch_oauth, $twitch_channel);
        $client->connect();

        if (!$client->isConnected()) {
            $this->getPrinter()->error("It was not possible to connect.");
            return;
        }

        $this->getPrinter()->info("Connected.\n");

        while (true) {
            $content = $client->read(512);

            //is it a ping?
            if (strstr($content, 'PING')) {
                $client->send('PONG :tmi.twitch.tv');
                continue;
            }

            //is it an actual msg?
            if (strstr($content, 'PRIVMSG')) {
                $this->printMessage($content);
                $this->checkCommands($content, $client);
                continue;
            }

            sleep(5);
            $client->sendMessage('o bot está ativo');
        }
    }

    public function printMessage($raw_message)
    {
        $chatMessage = $this->messageParser($raw_message);

        $nick = $chatMessage['nick'];
        $message = $chatMessage['message'];

        $style_nick = "info";
        $style_message = "dim";

        switch (true) {
            case (in_array($nick, $this->bots)):
                $style_nick = "info_alt";
                $style_message = "info";
                break;
            case (in_array($nick, $this->vips)):
                $style_nick = "alt";
                $style_message = "default";
                break;
            case (in_array($nick, $this->mods)):
                $style_nick = "success_alt";
                $style_message = "success";
                break;
            case ($nick ==  $this->getApp()->config->twitch_channel):
                $style_nick = "error_alt";
                $style_message = "error";
                break;
            default:
                $style_nick = "default";
                $style_message = "dim";
                break;
        }

        $this->getPrinter()->out($nick, $style_nick);
        $this->getPrinter()->out(': ');
        $this->getPrinter()->out($message, $style_message);
        $this->getPrinter()->newline();
    }

    public function checkCommands($raw_message, &$client)
    {
        $chatMessage = $this->messageParser($raw_message);

        $nick = $chatMessage['nick'];
        $message = $chatMessage['message'];

        if ($message[0] == '!') {
            $this->messageCommands($nick, $message, $client);
            $this->countCommands($nick, $message, $client);
        }
    }

    public function messageCommands($nick, $message, &$client)
    {
        if ($arrMsg = explode(' ', trim($message))) {
            $command = $arrMsg[0];
            if (array_key_exists($command, $this->messages)) {
                $this->getPrinter()->out($this->getApp()->config->twitch_user, 'alt');
                $this->getPrinter()->out(': ');
                $this->getPrinter()->out($this->messages[ $command ], 'alt');
                $this->getPrinter()->newline();
                $this->getPrinter()->newline();

                $client->sendMessage($this->messages[ $command ]);
            }
        }
    }

    public function countCommands($nick, $message, &$client)
    {
        if ($arrMsg = explode(' ', trim($message))) {
            $command = $arrMsg[0];
            if (array_key_exists($command, $this->countCommands)) {
                $count = ++$this->countCommands[$command]['count'];
                $message = str_replace('$count', $count, $this->countCommands[$command]['message']);

                $this->getPrinter()->out($this->getApp()->config->twitch_user, 'alt');
                $this->getPrinter()->out(': ');
                $this->getPrinter()->out($message, 'alt');
                $this->getPrinter()->newline();
                $this->getPrinter()->newline();

                $client->sendMessage($message);
            }
        }
    }

    public function messageParser($raw_message)
    {
        $parts = explode(":", $raw_message, 3);
        $nick_parts = explode("!", $parts[1]);

        $nick = $nick_parts[0];
        $message = $parts[2];

        return [
            'nick' => $nick,
            'message' => $message
        ];
    }
}
