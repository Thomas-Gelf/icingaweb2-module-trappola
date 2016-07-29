<?php

namespace Icinga\Module\Trappola;

use Predis\Client as PredisClient;
use Icinga\Application\Config;

class Redis
{
    protected static $redis;

    public static function instance()
    {
        if (self::$redis === null) {
            require_once dirname(__DIR__) . '/vendor/predis/autoload.php';

            // TODO: get rid of static handling
            $config = Config::module('trappola');
            self::$redis = new PredisClient(array(
                'host' => $config->get('redis', 'host', '127.0.0.1'),
                'port' => (int) $config->get('redis', 'port', 6379),
            ));
        }

        return self::$redis;
    }
}
