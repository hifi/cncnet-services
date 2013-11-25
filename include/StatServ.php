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

require_once 'Zend/Db.php';
require_once 'AbstractServ.php';

class StatServ extends AbstractServ
{
    protected $db;
    protected $nextStat = false;

    public function getNick() { return 'StatServ'; }
    public function getName() { return 'Statistics Service'; }

    public function __construct($options = array())
    {
        $this->db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => $options['db_path'], 'driver_options' => array(PDO::ATTR_TIMEOUT => 60)));

        $this->db->query("
            CREATE TABLE IF NOT EXISTS status(
                server      TEXT,
                users       INTEGER,
                channels    INTEGER,
                players     INTEGER,
                td          INTEGER,
                ra          INTEGER,
                created DATETIME DEFAULT (datetime())
            );
        ");

        $this->db->query("CREATE INDEX IF NOT EXISTS server_created_desc ON status(server, created DESC)");
        $this->db->query("CREATE INDEX IF NOT EXISTS server_players_desc ON status(server, players DESC)");

        $this->nextStat = time() + 1;
    }

    public function on265($prefix, $users) {
        $this->data['users'] = $users;
    }

    public function on322($prefix, $channel, $users) {
        if (preg_match('/^#ra_/', $channel)) {
            $this->data['ra']++;
        } else if (preg_match('/#td_/', $channel)) {
            $this->data['td']++;
        } else {
            $this->data['channels']++;
        }
        if ($channel == '#cncnet') {
            $this->data['players'] = (int)$users;
        }
    }

    public function on323() {
        $this->db->insert('status', $this->data);
    }

    private function setStatTimer()
    {
        $this->nextStat = time() + 60;
    }

    protected function doStat()
    {
        $this->data = array('server' => $this->server->getHost() . ':' . $this->server->getPort(), 'users' => 0, 'channels' => 0, 'players' => 0, 'td' => 0, 'ra' => 0);
        $this->putCommand('LUSERS');
        $this->putCommand('LIST');
        $this->setStatTimer();
    }

    public function think()
    {
        if (time() >= $this->nextStat) {
            $this->doStat();
        }

        return parent::think();
    }
}
