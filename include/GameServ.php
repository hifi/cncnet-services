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

require_once 'AbstractServ.php';
require_once 'Zend/Db.php';
require_once 'Zend/Validate/EmailAddress.php';

class GameServ extends AbstractServ
{
    protected $db;
    protected $authTimer = array();
    protected $authTimeout = 10;

    public function getNick() { return 'GameServ'; }
    public function getName() { return 'Game Service'; }

    public function __construct($options = array())
    {
        $this->db = Zend_Db::factory('Pdo_Sqlite', array('dbname' => $options['db_path'], 'driver_options' => array(PDO::ATTR_TIMEOUT => 60)));
        $this->authTimeout = array_key_exists('auth_timeout', $options) ? $options['auth_timeout'] : 10;

        $this->db->query("
            CREATE TABLE IF NOT EXISTS users(
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                nick            TEXT UNIQUE NOT NULL,
                password        TEXT NULL,
                salt            TEXT NULL,
                email           TEXT UNIQUE NOT NULL,
                mask            TEXT NULL,
                created DATETIME DEFAULT (datetime())
            );
        ");

        $this->db->query("CREATE INDEX IF NOT EXISTS users_nick ON users(nick)");
        $this->db->query("CREATE INDEX IF NOT EXISTS users_mask ON users(mask)");
        $this->db->query("CREATE INDEX IF NOT EXISTS users_nick_password ON users(nick, password)");
    }

    public function onPrivmsg($nick, $message) {
        $parts = explode(' ', $message);
        if (count($parts) == 0)
            return;

        if (strtoupper($parts[0]) == 'HELP') {
            $this->putNotice($nick, "Hello, $nick! I'm " . $this->getNick() . " and know the following commands: AUTH, GHOST and REGISTER.");
            return;
        }

        if (strtoupper($parts[0]) == 'AUTH') {

            if (count($parts) < 2) {
                $this->putNotice($nick, "Usage: AUTH <password>");
                return;
            }

            $user = $this->server->getUser($nick);
            $dbuser = $this->db->query($this->db->select()->from('users')->where('nick LIKE ?', $nick))->fetch();

            if ($dbuser && $dbuser['password'] == sha1($parts[1] . $dbuser['salt'])) {
                $this->putNotice($nick, "Authentication successful! You're now logged in as $nick.");
                $this->putCommand('MODE', $nick, '+R');
                $user->modes = str_replace('R', '', $user->modes) . 'R';
                unset($this->authTimer[$nick]);
                $this->db->update('users', array('mask' => $user->mask), $this->db->quoteInto('nick LIKE ?', $nick));
            } else {
                $this->putNotice($nick, "Invalid password or unknown account.");
            }

            return;
        }

        if (strtoupper($parts[0]) == 'GHOST') {

            if (count($parts) < 3) {
                $this->putNotice($nick, "Usage: GHOST <nick> <password>");
                return;
            }

            $dbuser = $this->db->query($this->db->select()->from('users')->where('nick LIKE ?', $parts[1]))->fetch();

            if ($dbuser && $dbuser['password'] == sha1($parts[2] . $dbuser['salt'])) {
                $this->putNotice($nick, "{$parts[1]} has been disconnected.");
                $this->putCommand('KILL', $parts[1], 'Killed by services.');
            } else {
                $this->putNotice($nick, "Invalid password or unknown account.");
            }

            return;
        }

        if (strtoupper($parts[0]) == 'REGISTER') {
            if (count($parts) < 4) {
                $this->putNotice($nick, "Usage: REGISTER <password> <email> <email again>");
                return;
            }

            if ((int)$this->db->query('SELECT COUNT(*) FROM users WHERE nick LIKE ?', $nick)->fetchColumn(0) > 0) {
                $this->putNotice($nick, "The nickname '$nick' is already registered, please select another one.");
                return;
            }

            if ($parts[2] != $parts[3]) {
                $this->putNotice($nick, "Email mismatch, try again.");
                return;
            }

            $validator = new Zend_Validate_EmailAddress();
            if (!$validator->isValid($parts[2])) {
                $this->putNotice($nick, "Invalid email address, try again.");
                return;
            }

            if ((int)$this->db->query('SELECT COUNT(*) FROM users WHERE email LIKE ?', $parts[1])->fetchColumn(0) > 0) {
                $this->putNotice($nick, "The email '{$parts[1]}' is already registered, please select another one.");
                return;
            }

            $salt = substr(str_shuffle(sha1(microtime())), 0, 32);
            $user = $this->server->getUser($nick);
            $ret = $this->db->insert('users', array(
                'nick'      => $nick,
                'password'  => sha1($parts[1] . $salt),
                'salt'      => $salt,
                'email'     => strtolower($parts[2]),
                'mask'      => $user->mask,
            ));

            if ($ret) {
                $this->putNotice($nick, "Registration successful! You're now logged in as $nick.");
                $this->putCommand('MODE', $nick, '+R');
                $user->modes = str_replace('R', '', $user->modes) . 'R';
            } else {
                $this->putNotice($nick, "Unknown error during registration. Sorry.");
            }
        }

        if (strtoupper($parts[0]) == 'SETPASS') {
            $user = $this->server->getUser($nick);
            if (strpos($user->modes, 'o') === false) {
                $this->putNotice($nick, "Only moderators can use this command.");
                return;
            }

            if (count($parts) < 3) {
                $this->putNotice($nick, "Usage: SETPASS <nick> <password>");
                return;
            }

            $salt = substr(str_shuffle(sha1(microtime())), 0, 32);
            $this->db->update('users', array(
                'password'  => sha1($parts[2] . $salt),
                'salt'      => $salt,
            ), $this->db->quoteInto('nick = ?', $parts[1]));

            $this->putNotice($nick, "New password set for {$parts[1]}.");
        }
    }

    public function onCommandNick($prefix, $nick) {
        $this->putCommand('MODE', $nick, '-R');

        $user = $this->server->getUser($nick);
        $user->modes = str_replace('R', '', $user->modes);

        if (strlen($prefix) > 0) {
            unset($this->authTimer[$prefix]);
        }

        if ((int)$this->db->query('SELECT COUNT(*) FROM users WHERE nick LIKE ?', $nick)->fetchColumn(0) > 0) {
            $this->putNotice($nick, "The nickname '$nick' is registered. Please type /msg " . $this->getNick() . " AUTH <password> in {$this->authTimeout} seconds or you will be renamed.");
            $this->authTimer[$nick] = time() + $this->authTimeout;
        }
    }

    public function onCommandUser($nick, $ident, $host) {
        $user = $this->server->getUser($nick);
        if ((int)$this->db->query('SELECT COUNT(*) FROM users WHERE mask LIKE ?', $user->mask)->fetchColumn(0) > 0) {
            $this->putNotice($nick, "Authentication successful! You're now logged in as $nick.");
            $this->putCommand('MODE', $nick, '+R');
            $user->modes = str_replace('R', '', $user->modes) . 'R';
            unset($this->authTimer[$nick]);
        }
    }

    public function onCommandQuit($nick) {
        unset($this->authTimer[$nick]);
        $this->db->update('users', array('mask' => NULL), $this->db->quoteInto('nick LIKE ?', $nick));
    }

    public function think() {
        $now = time();

        foreach ($this->authTimer as $nick => $time) {
            if ($now > $time) {
                $user = $this->server->getUser($nick);
                $this->putNotice($nick, "Authentication timed out, you've been renamed.");
                $this->putCommand('SVSNICK', $nick, 'UID' . strtoupper(substr(sha1($user->mask), 0, 13)));
                unset($this->authTimer[$nick]);
            }
        }
    }
}
