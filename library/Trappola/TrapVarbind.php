<?php

namespace Icinga\Module\Trappola;

use Icinga\Module\Trappola\Data\Db\DbObject;
use Icinga\Module\Trappola\TrapDb;

class TrapVarbind extends DbObject
{
    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $table = 'trap';

    protected $defaultProperties = array(
        'oid'         => null,
        'type'        => null,
        'value'       => null,
        'short_name'  => null,
        'mib_name'    => null,
        'description' => null,
    );
}
