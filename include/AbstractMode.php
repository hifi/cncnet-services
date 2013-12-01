<?php

abstract class AbstractMode
{
    protected $modes;

    public function updateModes($modes)
    {
        $adding = true;
        for ($i = 0; $i < strlen($modes); $i++) {
            $c = $modes[$i];
            if ($c == '+')
                $adding = true;
            else if ($c == '-')
                $adding = false;
            else {
                $this->modes = str_replace($c, '', $this->modes);
                if ($adding)
                    $this->modes .= $c;
            }
        }
    }

    public function hasMode($c)
    {
        return strpos($this->modes, $c) !== false;
    }
}
