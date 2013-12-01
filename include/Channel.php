<?php

require_once 'AbstractMode.php';

class Channel extends AbstractMode
{
    protected $name;
    protected $topic;
    protected $users = array();

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function setTopic($topic) { $this->topic = $topic; }
    public function getTopic() { return $this->topic; }

    public function getUserByNick($nick)
    {
        foreach ($users as $k => $user)
            if ($user->getNick() == $nick)
                return $user;

        return false;
    }

    public function addUser(User $user)
    {
        $this->removeUser($user);
        $this->users[] = $user;
    }

    public function removeUser(User $user)
    {
        foreach ($this->users as $k => $_user)
            if ($_user == $user)
                unset($users[$k]);
    }

    public function isEmpty() { return count($users) == 0; }

    public function toArray()
    {
        $users = array();
        foreach ($this->users as $user)
            $users[] = $user->toArray();

        return array(
            'name'  => $this->name,
            'topic' => $this->topic,
            'modes' => $this->modes,
            'users' => $users
        );
    }
}
