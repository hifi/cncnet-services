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

require_once 'StringTCPClient.php';

abstract class IRCProtocol extends StringTCPClient
{
    protected $nick;
    protected $lastMessage;

    public function __construct($host, $port)
    {
        $this->lastMessage = time();
        parent::__construct($host, $port);
    }

    public function putCommand()
    {
        $params = func_get_args();
        if (count($params) > 1) {
            $l = (count($params) - 1);
            if (strpos($params[$l], ' ') !== false)
                $params[$l] = ':' . $params[$l];
        }

        $this->putLine(implode(' ', $params));
    }

    abstract protected function onCommand($prefix, $command, array $params);

    private function _onCommand($prefix, $command, array $params)
    {
        $this->lastMessage = time();

        if (strpos($prefix, '@') === false && $command == 'PING' && count($params) == 1) {
            $this->putCommand('PONG', $params[0]);
            return;
        }

        $this->onCommand($prefix, $command, $params);
    }

    protected function onLine($l)
    {
        if (preg_match('/^(:([^ ]+) )?([^ ]+) ?(.*)/', $l, $m)) {
            $prefix = $m[2];
            $command = $m[3];
            $params = array();

            $tmp = explode(':', $m[4], 2);
            foreach (explode(' ', $tmp[0]) as $param) {
                if (strlen($param) > 0)
                    $params[] = $param;
            }

            if (count($tmp) > 1)
                $params[] = $tmp[1];

            $this->_onCommand($prefix, $command, $params);
        } else {
            echo "INVALID <- " . $l . "\n";
        }
    }

    protected function onDisconnect()
    {
        echo "Disconnected from server.\n";
    }

    public function think()
    {
        if (time() - $this->lastMessage > 300) {
            throw new Exception('Ping timeout, disconnecting.');
        }

        return parent::think();
    }
}
