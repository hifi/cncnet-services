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

set_include_path(get_include_path() . PATH_SEPARATOR . 'include');

require_once 'Server.php';

date_default_timezone_set('UTC');

if (!file_exists('config.inc.php')) {
    echo "config.inc.php is missing.\n";
    exit;
}

$config = require_once 'config.inc.php';

while (1) {
    $server = new Server($config['host'], $config['port'], $config['password'], $config['my_host'], $config['my_name']);
    $server->setDebug(true);
    foreach ($config['services'] as $service => $options) {
        require_once $service . '.php';
        $server->registerService(new $service($options));
    }
    $server->run();
    echo "Disconnected from server, reconnecting...\n";
    sleep(5);
}
