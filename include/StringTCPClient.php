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

abstract class StringTCPClient
{
    const INBUF_SIZE = 8192;
    const OUTBUF_SIZE = 2048;

    protected $host;
    protected $port;
    protected $s;
    protected $inbuf;
    protected $outbuf;
    protected $debug;

    public function __construct($host, $port)
    {
        $this->host = $host;
        $this->port = $port;
        $this->s = false;
        $this->inbuf = '';
        $this->outbuf = '';
    }

    public function getHost() { return $this->host; }
    public function getPort() { return $this->port; }
    public function setDebug($enabled) { $this->debug = (boolean)$enabled; }

    abstract protected function onLine($l);
    abstract protected function onConnect();
    abstract protected function onDisconnect();

    protected function putLine($l)
    {
        $this->outbuf .= $l . "\r\n";
        if ($this->debug) echo "-> $l\n";
    }

    private function onRead()
    {
        while (($nl = strpos($this->inbuf, "\n")) > 0) {
            $line = trim(substr($this->inbuf, 0, $nl));
            if ($this->debug) echo "<- $line\n";
            $this->onLine($line);
            $this->inbuf = substr($this->inbuf, $nl + 1, strlen($this->inbuf) - $nl - 1);
        }
    }

    private function canRead()
    {
        $buf = null;
        $ret = socket_recv($this->s, $buf, 2048, 0);
        $this->inbuf .= $buf;
        if (strlen($this->inbuf) > static::INBUF_SIZE)
            throw new Exception('Input buffer exhausted.');

        if (strlen($this->inbuf) > 0)
            $this->onRead();

        return $ret;
    }

    private function canWrite()
    {
        socket_send($this->s, $this->outbuf, strlen($this->outbuf), 0);
        $this->outbuf = '';
    }

    public function run()
    {
        $this->s = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!socket_connect($this->s, $this->host, $this->port)) {
            socket_close($this->s);
            return false;
        }

        $this->onConnect();

        try {
            while (1) {
                $read = array($this->s);
                $write = array();
                $except = array();

                if ($this->think())
                    $write[] = $this->s;

                $ret = socket_select($read, $write, $except, 1);
                if ($ret === false)
                    break;

                if (count($read) > 0)
                    if ($this->canRead() == 0)
                        break;

                if (count($write) > 0)
                    $this->canWrite();
            }
        } catch (Exception $e) {
            echo 'Exception: ' . $e->getMessage() . "\n";
        }

        $this->onDisconnect();

        socket_close($this->s);
    }

    protected function think()
    {
        if (strlen($this->outbuf) > static::OUTBUF_SIZE)
            throw new Exception('Output buffer exhausted.');

        return (strlen($this->outbuf) > 0);
    }
}
