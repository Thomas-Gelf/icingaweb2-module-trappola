<?php

namespace Icinga\Module\Trappola;

use Predis\Client as PredisClient;

class Redis
{
    protected static $redis;

    public static function instance()
    {
        if (self::$redis === null) {
            require_once dirname(__DIR__) . '/vendor/predis/autoload.php';
            self::$redis = new PredisClient();
        }

        return self::$redis;
    }
}
