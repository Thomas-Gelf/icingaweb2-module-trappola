<?php

namespace Icinga\Module\Trappola;

use Icinga\Module\Trappola\Data\Db\DbObject;
use Icinga\Module\Trappola\TrapDb;

class Trap extends DbObject
{
    protected $oidCache;

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $table = 'trap';

    protected $varbinds;

    protected $varbind_idx;

    protected $defaultProperties = array(
        'id'          => null,
        'listener_id' => null,
        'timestamp'   => null,
        'host_name'   => null,
        'src_address'      => null,
        'src_port'         => null,
        'dst_address'      => null,
        'dst_port'         => null,
        'network_protocol' => null,
        'auth' => null,
        'message' => null,
        'sys_uptime' => null,
        'type' => null,
        'version' => null,
        'requestid' => null,
        'transactionid' => null,
        'messageid' => null,
        'oid' => null,
        'short_name' => null,
        'mib_name' => null,
        'transport' => null,
        'security' => null,
        'v3_sec_level' => null,
        'v3_sec_name' => null,
        'v3_sec_engine' => null,
        'v3_ctx_name' => null,
        'v3_ctx_engine' => null,
    );

    public function resolveOid($oid)
    {
        if ($this->resolvesOid($oid)) {
            return $this->oidCache[$oid];
        } else {
            return $this->getUnresolved($oid);
        }
    }

    public function onInsert()
    {
        $db = $this->getDb();
        foreach ($this->varbinds as $varbind) {
            $db->insert('trap_varbind', array(
                'trap_id' => $this->id,
                'oid'     => $varbind->oid,
                'type'    => $varbind->type,
                'value'   => $varbind->value,
            ));
        }
    }

    protected function fillOidCache()
    {
        $this->oidCache = array();

        if (! $this->hasConnection()) {
            return;
        }

        $oids = array($this->oid => $this->oid);
        foreach ($this->getVarbinds() as $var) {
            $oids[$var->oid] = $var->oid;
        }

        $db = $this->getDb();
        $resolved = $db->fetchAll(
            $db->select()
               ->from('trap_oidcache')
               ->where('oid IN (?)', array_values($oids))
        );

        foreach ($resolved as $cached) {
            $cached->short_name = preg_replace('/\.0$/', '', $cached->short_name);
            $this->oidCache[$cached->oid] = $cached;
            unset($oids[$cached->oid]);
        }

        foreach ($oids as $oid) {
            $this->oidCache[$oid] = $this->getUnresolved($oid);
        }
    }

    protected function getUnresolved($oid)
    {
        return (object) array(
            'oid'         => $oid,
            'short_name'  => null,
            'mib_name'    => null,
            'description' => null,
        );
    }

    public function resolvesOid($oid)
    {
        if ($this->oidCache === null) {
            $this->fillOidCache();
        }
        
        return array_key_exists($oid, $this->oidCache)
            && $this->oidCache[$oid]->mib_name !== null;
    }

    public function getOidDescription($oid)
    {
        if ($this->resolvesOid($oid)) {
            return $this->resolveOid($oid)->description;
        }

        return null;
    }

    public function getOidName($oid)
    {
        if ($this->resolvesOid($oid)) {
            $res = $this->resolveOid($oid);
            return sprintf('%s::%s', $res->mib_name, $res->short_name);
        }

        return $oid;
    }

    public function getOidMibName($oid)
    {
        if ($this->resolvesOid($oid)) {
            return $this->resolveOid($oid)->mib_name;
        }

        return null;
    }

    public function getOidShortName($oid)
    {
        if ($this->resolvesOid($oid)) {
            return $this->resolveOid($oid)->short_name;
        }

        return null;
    }

    public function setVarbinds($value)
    {
        $this->varbinds = array();
        $this->varbind_idx = array();
        foreach (array_values($value) as $key => $varbind) {
            $this->varbinds[$key] = $varbind;
            $this->varbind_idx[$varbind->oid] = $key;
        }
        return $this;
    }

    public function getVarbind($oid)
    {
        if (array_key_exists($oid, $this->varbind_idx)) {
            return $this->varbinds[$this->varbind_idx[$oid]];
        }

        return null;
    }

    public function getVarbinds()
    {
        if ($this->varbinds === null) {
            if ($this->hasBeenLoadedFromDb()) {
                $db = $this->getConnection();
                $this->varbinds = TrapVarbind::loadAll($db, $db->getTrapVarsQuery($this->id));
            } else {
                $this->varbinds = array();
            }
        }

        return $this->varbinds;
    }
}
