<?php

namespace Icinga\Module\Trappola;

use Icinga\Module\Trappola\Data\Db\DbObject;
use Icinga\Module\Trappola\TrapDb;
use Icinga\Module\Trappola\Util;

class Trap extends DbObject
{
    protected static $oidCache;

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $table = 'trap';

    protected $varbinds;

    protected $varbind_idx;

    protected $varbindShortIdx;

    protected $defaultProperties = array(
        'id'               => null,
        'listener_id'      => null,
        'timestamp'        => null,
        'host_name'        => null,
        'src_address'      => null,
        'src_port'         => null,
        'dst_address'      => null,
        'dst_port'         => null,
        'network_protocol' => null,
        'auth'             => null,
        'message'          => null,
        'sys_uptime'       => null,
        'type'             => null,
        'version'          => null,
        'requestid'        => null,
        'transactionid'    => null,
        'messageid'        => null,
        'oid_checksum'     => null,
        'oid'              => null,
        'short_name'       => null,
        'mib_name'         => null,
        'acknowledged'     => 'n',
        'transport'        => null,
        'security'         => null,
        'v3_sec_level'     => null,
        'v3_sec_name'      => null,
        'v3_sec_engine'    => null,
        'v3_ctx_name'      => null,
        'v3_ctx_engine'    => null,
    );

    public function resolveTrapName()
    {
        $trapOid = $this->resolveOid($this->oid);
        $this->mib_name = $trapOid->mib_name;
        $this->short_name = $trapOid->short_name;
        if ($this->message === null) {
            $this->message = $trapOid->description;
        }
    }

    public function setOid($oid)
    {
        $this->oid_checksum = sha1($oid, true);
        return $this->reallySet('oid', $oid);
    }

    public function resolveOid($oid)
    {
        if ($this->resolvesOid($oid)) {
            return self::$oidCache[$oid];
        } else {
            return $this->getUnresolved($oid);
        }
    }

    public function onInsert()
    {
        $db = $this->getDb();
        foreach ($this->varbinds as $varbind) {
            $db->insert('trap_varbind', array(
                'trap_id'      => $this->id,
                'oid'          => $varbind->oid,
                'oid_checksum' => sha1($varbind->oid, true),
                'type'         => $varbind->type,
                'value'        => $varbind->value,
            ));
        }
    }

    protected function fillOidCache()
    {
        self::$oidCache = array();

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
            self::$oidCache[$cached->oid] = $cached;
            unset($oids[$cached->oid]);
        }

        foreach ($oids as $oid) {
            self::$oidCache[$oid] = $this->getUnresolved($oid);
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
        if (self::$oidCache === null) {
            $this->fillOidCache();
        }

        return array_key_exists($oid, self::$oidCache)
            && self::$oidCache[$oid]->mib_name !== null;
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
        if ($this->varbinds === null) {
            $this->getVarbinds();
        }

        if (array_key_exists($oid, $this->varbind_idx)) {
            return $this->varbinds[$this->varbind_idx[$oid]];
        }

        return null;
    }

    public function getVarbinds()
    {
        if ($this->varbinds === null) {
            //$this->varbind_idx = array();
            if ($this->hasBeenLoadedFromDb()) {
                $db = $this->getConnection();
                // $this->varbinds = TrapVarbind::loadAll($db, $db->getTrapVarsQuery($this->id));
                $varbinds = TrapVarbind::loadAll($db, $db->getTrapVarsQuery($this->id));
                $this->setVarbinds($varbinds);
                //foreach ($this->varbinds as $key => $varbind) {
                //    $this->varbind_idx[$varbind->oid] = $key;
                //}
            } else {
                $this->varbinds = array();
            }
        }

        return $this->varbinds;
    }

    public static function fromSerializedJson($json, TrapDb $db = null)
    {
        return static::create((array) json_decode($json), $db);
    }

    public function toSerializedJson()
    {
        return json_encode($this->toSerializedObject());
    }

    protected function toSerializedObject()
    {
        $blacklist = array(
            'id',
            'mib_name',
            'host_name',
            'short_name',
            'message',
            'timestamp',
            'acknowledged',
            'oid_checksum'
        );

        $binary = array('v3_sec_engine', 'v3_ctx_engine');

        $props = array();
        foreach ($this->getProperties() as $key => $val) {
            if ($val === null) {
                continue;
            }

            if (in_array($key, $blacklist)) {
                continue;
            }

            if (in_array($key, $binary)) {
                $val = Util::bin2hex($val);
            }
            $props[$key] = $val;
        }

        $props['varbinds'] = array();
        foreach ($this->getVarbinds() as $varbind) {
            $props['varbinds'][] = $varbind->toSerializedObject();
        }

        return (object) $props;
    }
}
