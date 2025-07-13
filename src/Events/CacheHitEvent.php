<?php

namespace IDigAcademy\AutoCache\Events;

use Illuminate\Foundation\Events\Dispatchable;

class CacheHitEvent
{
    use Dispatchable;

    public $key;
    public $tags;

    public function __construct($key, $tags = [])
    {
        $this->key = $key;
        $this->tags = $tags;
    }
}