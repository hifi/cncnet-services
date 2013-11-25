<?php
/*
 * Copyright (c) 2013 Toni Spets <toni.spets@iki.fi>
 * 
 * Permission to use, copy, modify, and distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

require_once 'IRCProtocol.php';
require_once 'IServer.php';

class Server extends IRCProtocol implements IServer
{
    protected $password;
    protected $serverHost;
    protected $serverName;
    protected $services = array();
    protected $users = array();

    const RPL_WHOISUSER     = 311;
    const RPL_WHOISSERVER   = 312;
    const RPL_ENDOFWHOIS    = 318;

    public function __construct($host, $port, $password, $serverHost = 'services', $serverName = 'Services')
    {
        $this->password = $password;
        $this->serverHost = $serverHost;
        $this->serverName = $serverName;
        parent::__construct($host, $port);
    }

    public function registerService(IService $service)
    {
        $this->services[strtolower($service->getNick())] = $service;
        $service->setServer($this);
    }

    public function putServerCommand()
    {
        $args = func_get_args();
        array_unshift($args, ':' . $this->serverHost);
        return call_user_func_array(array($this, 'putCommand'), $args);
    }

    public function getUser($nick)
    {
        return array_key_exists($nick, $this->users) ? $this->users[$nick] : false;
    }

    // provide register and whois for services
    protected function onCommand($prefix, $command, array $params) {
        if ($command == 'PASS') {
            foreach ($this->services as $service) {
                $this->putCommand('NICK', $service->getNick(), 1);
                $this->putCommand(':' . $service->getNick(), 'USER', strtolower($service->getNick()), $this->serverHost, $this->serverHost, $service->getName());
                $this->putCommand(':' . $service->getNick(), 'MODE', $service->getNick(), '+ioqB');
            }
            return;
        }

        $target = strtolower($params[0]);

        if ($command == 'NICK' && strlen($prefix) > 0) {
            $user = $this->users[$prefix];
            unset($this->users[$prefix]);
            $user->nick = $params[0];
            $this->users[$params[0]] = $user;
        }

        if ($command == 'USER') {
            $this->users[$prefix] = (object)array(
                'nick'      => $prefix,
                'ident'     => $params[0],
                'host'      => $params[1],
                'server'    => $params[2],
                'name'      => $params[3],
                'mask'      => $prefix . '!' . $params[0] . '@' . $params[1],
            );
        }

        if ($command == 'QUIT') {
            unset($this->users[$prefix]);
        }

        if (count($params) > 0 && array_key_exists($target, $this->services)) {
            $service = $this->services[$target];

            if ($command == 'WHOIS') {
                $this->putServerCommand(self::RPL_WHOISUSER, $prefix, $service->getNick(), strtolower($service->getNick()), $this->serverHost, '*', $service->getName());
                $this->putServerCommand(self::RPL_WHOISSERVER, $prefix, $service->getNick(), $this->serverHost, $this->serverName);
                $this->putServerCommand(self::RPL_ENDOFWHOIS, $prefix, $service->getNick(), 'End of WHOIS list');
            } else {
                array_shift($params);
                array_unshift($params, $prefix);
                call_user_func_array(array($service, 'on' . ucfirst(strtolower($command))), $params);
            }
        } else {
            array_unshift($params, $prefix);
            foreach ($this->services as $service) {
                call_user_func_array(array($service, 'onCommand' . ucfirst(strtolower($command))), $params);
            }
        }
    }

    protected function onConnect()
    {
        echo "Service: Connected.\n";
        $this->putCommand('PASS', $this->password);
        $this->putCommand('SERVER', $this->serverHost, 1, $this->serverName);
    }

    public function think()
    {
        foreach ($this->services as $service)
            $service->think();

        return parent::think();
    }
}
