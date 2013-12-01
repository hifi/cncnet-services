<?php

require_once 'AbstractMode.php';

class User extends AbstractMode
{
    protected $nick;
    protected $ident;
    protected $host;
    protected $server;
    protected $name;
    protected $mask;

    public function __construct($nick, $ident, $host, $server, $name)
    {
        $this->ident    = $ident;
        $this->host     = $host;
        $this->server   = $server;
        $this->name     = $name;

        $this->setNick($nick);
    }

    public function getNick() { return $this->nick; }
    public function getMask() { return $this->mask; }

    public function setNick($nick)
    {
        $this->nick = $nick;
        $this->mask = $this->nick . '!' . $this->ident . '@' . $this->host;
    }

    public function isOper() { return $this->hasMode('o'); }
    public function isRegistered() { return $this->hasMode('R'); }

    public function toArray()
    {
        return array(
            'nick'      => $this->nick,
            'ident'     => $this->ident,
            'host'      => $this->host,
            'server'    => $this->server,
            'name'      => $this->name,
            'mask'      => $this->mask,
            'modes'     => $this->modes
        );
    }
}
