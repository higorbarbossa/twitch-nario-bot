<?php

namespace App;

class TwitchChatClient
{
    protected $socket;
    protected $nick;
    protected $channel;
    protected $oauth;

    public static $host = "irc.chat.twitch.tv";
    public static $port = "6667";

    public function __construct($nick, $oauth, $channel = null)
    {
        $this->nick = $nick;
        $this->channel = $channel ?? $nick;
        $this->oauth = $oauth;
    }

    public function connect()
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (socket_connect($this->socket, self::$host, self::$port) === false) {
            return null;
        }

        $this->authenticate();
        $this->setNick();
        $this->joinChannel(
            $this->channel
        );
    }

    public function authenticate()
    {
        $this->send(sprintf("PASS %s", $this->oauth));
    }

    public function setNick()
    {
        $this->send(sprintf("NICK %s", $this->nick));
    }

    public function joinChannel($channel)
    {
        $this->send(sprintf("JOIN #%s", $channel));
    }

    public function getLastError()
    {
        return socket_last_error($this->socket);
    }

    public function isConnected()
    {
        return !is_null($this->socket);
    }

    public function read($size = 256)
    {
        if (!$this->isConnected()) {
            return null;
        }

        return socket_read($this->socket, $size);
    }

    public function send($message)
    {
        if (!$this->isConnected()) {
            return null;
        }

        return socket_write($this->socket, $message . "\n");
    }

    public function sendMessage($message)
    {
        $this->send('PRIVMSG #' . $this->channel . ' :' . $message);
    }

    public function close()
    {
        socket_close($this->socket);
    }
}
