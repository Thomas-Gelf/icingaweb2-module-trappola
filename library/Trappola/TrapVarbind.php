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
// ???? UNUSED???
        'oid'          => null,
        'oid_checksum' => null,
        'type'         => null,
        'value'        => null,
        'short_name'   => null,
        'mib_name'     => null,
        'description'  => null,
    );

    public function setOid($oid)
    {
        $this->oid_checksum = sha1($oid, true);
        return $this->reallySet('oid', $oid);
    }

    public function toSerializedObject()
    {
        return (object) array(
            'oid'   => $this->oid,
            'type'  => $this->type,
            'value' => $this->value
        );
    }
}
