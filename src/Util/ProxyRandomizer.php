<?php

namespace App\Util;

trait ProxyRandomizer
{
    /**
     * @var array<string>
     */
    private $proxies = [];

    /**
     * @return string|null
     */
    private function getRandomProxy(): string
    {
        return  $this->proxies[array_rand($this->proxies)];
    }
}