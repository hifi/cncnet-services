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

require_once 'IService.php';

abstract class AbstractServ implements IService
{
    protected $server;

    abstract public function getNick();
    abstract public function getName();

    public function setServer(IServer $server) {
        $this->server = $server;
    }

    protected function putNotice($nick, $message) {
        $this->server->putCommand(':' . $this->getNick(), 'NOTICE', $nick, $message);
    }

    protected function putPrivmsg($nick, $message) {
        $this->server->putCommand(':' . $this->getNick(), 'PRIVMSG', $nick, $message);
    }

    protected function putCommand() {
        $params = func_get_args();
        array_unshift($params, ':' . $this->getNick());
        call_user_func_array(array($this->server, 'putCommand'), $params);
    }

    public function __call($name, $arguments)
    {
        // unhandled callback catcher
    }
}
