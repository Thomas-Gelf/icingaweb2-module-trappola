<?php

/**
 * This file ...
 *
 * @copyright  Icinga Team <team@icinga.org>
 * @license  GPLv2 http://www.gnu.org/licenses/gpl-2.0.html
 */
namespace Icinga\Module\Trappola\Data\Db;

use Icinga\Data\Db\DbConnection;
use Exception;

/**
 * Base class for ...
 */
abstract class DbObject
{
    /**
     * DbConnection
     */
    protected $connection;

    /**
     * Zend_Db_Adapter_Abstract: DB Handle
     */
    protected $db;

    /**
     * Table name. MUST be set when extending this class
     */
    protected $table;

    /**
     * Default columns. MUST be set when extending this class. Each table
     * column MUST be defined with a default value. Default value may be null.
     */
    protected $defaultProperties;

    /**
     * Properties as loaded from db
     */
    protected $loadedProperties;

    /**
     * Whether at least one property has been modified
     */
    protected $hasBeenModified = false;

    /**
     * Whether this object has been loaded from db
     */
    protected $loadedFromDb = false;

    /**
     * Object properties
     */
    protected $properties = array();

    /**
     * Property names that have been modified since object creation
     */
    protected $modifiedProperties = array();

    /**
     * Unique key name, could be primary
     */
    protected $keyName;

    /**
     * Set this to an eventual autoincrementing column. May equal $keyName
     */
    protected $autoincKeyName;

    /**
     * Constructor is not accessible and should not be overridden
     */
    protected function __construct()
    {
        if ($this->table === null
            || $this->keyName === null
            || $this->defaultProperties === null
        ) {
            throw new Exception("Someone extending this class didn't RTFM");
        }

        $this->properties = $this->defaultProperties;
        $this->beforeInit();
    }

    public function getTableName()
    {
        return $this->table;
    }

    /**
     * Kann überschrieben werden, um Kreuz-Checks usw vor dem Speichern durch-
     * zuführen - die Funktion ist aber public und erlaubt jederzeit, die Kon-
     * sistenz eines Objektes bei bedarf zu überprüfen.
     *
     * @return boolean  Ob der Wert gültig ist
     */
    public function validate()
    {
        return true;
    }


    /************************************************************************\
     * Nachfolgend finden sich ein paar Hooks, die bei Bedarf überschrieben *
     * werden können. Wann immer möglich soll darauf verzichtet werden,     *
     * andere Funktionen (wie z.B. store()) zu überschreiben.               *
    \************************************************************************/

    /**
     * Wird ausgeführt, bevor die eigentlichen Initialisierungsoperationen
     * (laden von Datenbank, aus Array etc) starten
     *
     * @return void
     */
    protected function beforeInit() {}

    /**
     * Wird ausgeführt, nachdem mittels ::factory() ein neues Objekt erstellt
     * worden ist.
     *
     * @return void
     */
    protected function onFactory() {}

    /**
     * Wird ausgeführt, nachdem mittels ::factory() ein neues Objekt erstellt
     * worden ist.
     *
     * @return void
     */
    protected function onLoadFromDb() {}

    /**
     * Wird ausgeführt, bevor ein Objekt abgespeichert wird. Die Operation
     * wird aber auf jeden Fall durchgeführt, außer man wirft eine Exception
     *
     * @return void
     */
    protected function beforeStore() {}

    /**
     * Wird ausgeführt, nachdem ein Objekt erfolgreich gespeichert worden ist
     *
     * @return void
     */
    protected function onStore() {}

    /**
     * Wird ausgeführt, nachdem ein Objekt erfolgreich der Datenbank hinzu-
     * gefügt worden ist
     *
     * @return void
     */
    protected function onInsert() {}

    /**
     * Wird ausgeführt, nachdem bestehendes Objekt erfolgreich der Datenbank
     * geändert worden ist
     *
     * @return void
     */
    protected function onUpdate() {}

    /**
     * Wird ausgeführt, bevor ein Objekt gelöscht wird. Die Operation wird
     * aber auf jeden Fall durchgeführt, außer man wirft eine Exception
     *
     * @return void
     */
    protected function beforeDelete() {}

    /**
     * Wird ausgeführt, nachdem bestehendes Objekt erfolgreich aud der
     * Datenbank gelöscht worden ist
     *
     * @return void
     */
    protected function onDelete() {}

    /**
     * Set database connection
     *
     * @param DbConnection $connection Database connection
     *
     * @return self
     */
    public function setConnection(DbConnection $connection)
    {
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
        return $this;
    }

    /**
     * Getter
     *
     * @param string $property Property
     *
     * @return mixed
     */
    public function get($property)
    {
        $func = 'get' . ucfirst($property);
        if (substr($func, -2) === '[]') {
            $func = substr($func, 0, -2);
        }
        // TODO: id check avoids collision with getId. Rethink this.
        if ($property !== 'id' && method_exists($this, $func)) {
            return $this->$func();
        }

        if (! array_key_exists($property, $this->properties)) {
            throw new Exception(sprintf('Trying to get invalid property "%s"', $property));
        }
        return $this->properties[$property];
    }

    public function hasProperty($key)
    {
        if (array_key_exists($key, $this->properties)) {
            return true;
        }
        $func = 'get' . ucfirst($key);
        if (substr($func, -2) === '[]') {
            $func = substr($func, 0, -2);
        }
        if (method_exists($this, $func)) {
            return true;
        }
        return false;
    }

    /**
     * Generic setter
     *
     * @param string $property
     * @param mixed  $value
     *
     * @return array
     */
    public function set($key, $value)
    {
        $key = (string) $key;
        if ($value === '') {
            $value = null;
        }
        if (! $this->hasProperty($key)) {
            throw new Exception(sprintf('Trying to set invalid key %s', $key));
        }
        $func = 'validate' . ucfirst($key);
        if (method_exists($this, $func) && $this->$func($value) !== true) {
            throw new Exception(
                sprintf('Got invalid value "%s" for "%s"', $value, $key)
            );
        }
        $func = 'munge' . ucfirst($key);
        if (method_exists($this, $func)) {
            $value = $this->$func($value);
        }
        if ($value === $this->get($key)) {
            return $this;
        }
        if ($key === $this->getKeyName() && $this->hasBeenLoadedFromDb()) {
            throw new Exception('Changing primary key is not allowed');
        }
        $func = 'set' . ucfirst($key);
        if (substr($func, -2) === '[]') {
            $func = substr($func, 0, -2);
        }
        if (method_exists($this, $func)) {
            return $this->$func($value);
        }

        return $this->reallySet($key, $value);
    }

    protected function reallySet($key, $value)
    {
        if ($value === $this->$key) {
            return $this;
        }
        $this->hasBeenModified = true;
        $this->modifiedProperties[$key] = true;
        $this->properties[$key] = $value;
        return $this;
    }

    /**
     * Magic getter
     *
     * @return mixed
     */
    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * Magic setter
     *
     * @param  string  $key  Key
     * @param  mixed   $val  Value
     *
     * @return void
     */
    public function __set($key, $val)
    {
        $this->set($key, $val);
    }

    /**
     * Magic isset check
     *
     * @return boolean
     */
    public function __isset($key)
    {
        return array_key_exists($key, $this->properties);
    }

    /**
     * Magic unsetter
     *
     * @return void
     */
    public function __unset($key)
    {
        if (! array_key_exists($key, $this->properties)) {
            throw new Exception('Trying to unset invalid key');
        }
        $this->properties[$key] = $this->defaultProperties[$key];
    }

    /**
     * Führt die Operation set() für jedes Element (key/value Paare) der über-
     * gebenen Arrays aus
     *
     * @param  array  $data  Array mit den zu setzenden Daten
     * @return self
     */
    public function setProperties($props)
    {
        if (! is_array($props)) {
            throw new Exception('Array required, got ' . gettype($props));
        }
        foreach ($props as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

    /**
     * Return an array with all object properties
     *
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }

    public function listProperties()
    {
        return array_keys($this->properties);
    }

    /**
     * Return all properties that changed since object creation
     *
     * @return array
     */
    public function getModifiedProperties()
    {
        $props = array();
        foreach (array_keys($this->modifiedProperties) as $key) {
            if ($key === $this->keyName || $key === $this->autoincKeyName) continue;
            $props[$key] = $this->properties[$key];
        }
        return $props;
    }

    /**
     * Whether this object has been modified
     *
     * @return bool
     */
    public function hasBeenModified()
    {
        return $this->hasBeenModified;
    }

    /**
     * Whether the given property has been modified
     *
     * @param  string   $key Property name
     * @return boolean
     */
    protected function hasModifiedProperty($key)
    {
        return array_key_exists($key, $this->modifiedProperties);
    }

    /**
     * Unique key name
     *
     * @return string
     */
    public function getKeyName()
    {
        return $this->keyName;
    }

    /**
     * Autoinc key name
     *
     * @return string
     */
    public function getAutoincKeyName()
    {
        return $this->autoincKeyName;
    }

    /**
     * Return the unique identifier
     *
     * // TODO: may conflict with ->id
     *
     * @return string
     */
    public function getId()
    {
        // TODO: Doesn't work for array() / multicol key
        if (is_array($this->keyName)) {
            $id = array();
            foreach ($this->keyName as $key) {
                if (! isset($this->properties[$key])) {
                    return null; // Really?
                }
                $id[$key] = $this->properties[$key];
            }
            return $id;
         } else {
            if (isset($this->properties[$this->keyName]))
            {
                return $this->properties[$this->keyName];
            }
        }
        return null;
    }

    /**
     * Get the autoinc value if set
     *
     * @return string
     */
    public function getAutoincId()
    {
        if (isset($this->properties[$this->autoincKeyName]))
        {
            return $this->properties[$this->autoincKeyName];
        }
        return null;
    }

    /**
     * Liefert das benutzte Datenbank-Handle
     *
     * @return Zend_Db_Adapter_Abstract
     */
    public function getDb()
    {
        return $this->db;
    }

    public function hasConnection()
    {
        return $this->connection !== null;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Lädt einen Datensatz aus der Datenbank und setzt die entsprechenden
     * Eigenschaften dieses Objekts
     *
     * @return self
     */
    protected function loadFromDb()
    {
        $select = $this->db->select()->from($this->table)->where($this->createWhere());
        $properties = $this->db->fetchRow($select);

        if (empty($properties)) {
            $msg = sprintf('Got no "%s" data for: %s', $this->table, $this->getId());
            throw new Exception($msg);
        }

        return $this->setDbProperties($properties);
    }

    protected function setDbProperties($properties)
    {
        foreach ($properties as $key => $val) {
            if (! array_key_exists($key, $this->properties)) {
                throw new Exception(sprintf(
                    'Trying to set invalid %s key "%s". DB schema change?',
                    $this->table,
                    $key
                ));
            }
            if ($val === null) {
                $this->properties[$key] = null;
            } elseif (is_resource($val)) {
                $this->properties[$key] = stream_get_contents($val);
            } else {
                $this->properties[$key] = (string) $val;
            }
        }
        $this->loadedFromDb = true;
        $this->loadedProperties = $this->properties;
        $this->hasBeenModified = false;
        $this->onLoadFromDb();
        return $this;
    }

    public function getOriginalProperties()
    {
        return $this->loadedProperties;
    }

    public function hasBeenLoadedFromDb()
    {
        return $this->loadedFromDb;
    }

    /**
     * Ändert den entsprechenden Datensatz in der Datenbank
     *
     * @return int  Anzahl der geänderten Zeilen
     */
    protected function updateDb()
    {
        $properties = $this->getModifiedProperties();
        if (empty($properties)) {
            // Fake true, we might have manually set this to "modified"
            return true;
        }

        // TODO: Remember changed data for audit and log
        return $this->db->update(
            $this->table,
            $properties,
            $this->createWhere()
        );

    }

    /**
     * Fügt der Datenbank-Tabelle einen entsprechenden Datensatz hinzu
     *
     * @return int  Anzahl der betroffenen Zeilen
     */
    protected function insertIntoDb()
    {
        $properties = $this->getProperties();
        if ($this->autoincKeyName !== null) {
            unset($properties[$this->autoincKeyName]);
        }
        if ($this->connection->getDbType() === 'pgsql') {
            foreach ($properties as $key => $value) {
                if (preg_match('/checksum$/', $key)) {
                    $properties[$key] = Util::pgBinEscape($value);
                }
            }
        }

        return $this->db->insert($this->table, $properties);
    }

    /**
     * Store object to database
     *
     * @return boolean  Whether storing succeeded
     */
    public function store(DbConnection $db = null)
    {
        if ($db !== null) {
            $this->setConnection($db);
        }

        if ($this->validate() !== true) {
            throw new Exception(sprintf(
                '%s[%s] validation failed',
                $this->table,
                $this->getId()
            ));
        }

        if ($this->hasBeenLoadedFromDb() && ! $this->hasBeenModified()) {
            return true;
        }

        $this->beforeStore();
        $table = $this->table;
        $id = $this->getId();
        if (is_array($id)) {
            $logId = json_encode($id);
        } else {
            $logId = $id;
        }
        $result = false;

        try {
            if ($this->hasBeenLoadedFromDb()) {
                if ($this->updateDb()) {
                    /*throw new Exception(
                        sprintf('%s "%s" has been modified', $table, $id)
                    );*/
                    $result = true;
                    $this->onUpdate();
                } else {
                    throw new Exception(
                        sprintf('FAILED storing %s "%s"', $table, $logId));
                }
            } else {
                if ($id && $this->existsInDb()) {
                    throw new Exception(
                        sprintf('Trying to recreate %s (%s)', $table, $logId)
                    );
                }

                if ($this->insertIntoDb()) {
                    $id = $this->getId();
                    if ($this->autoincKeyName) {
                        $this->properties[$this->autoincKeyName] = $this->db->lastInsertId();
                        if (! $id) {
                            $id = '[' . $this->properties[$this->autoincKeyName] . ']';
                        }
                    }
                    // $this->log(sprintf('New %s "%s" has been stored', $table, $id));
                    $this->onInsert();
                    $result = true;
                } else {
                    throw new Exception(
                        sprintf('FAILED to store new %s "%s"', $table, $logId)
                    );
                }
            }

        } catch (Exception $e) {
            throw new Exception(
                sprintf(
                    'Storing %s[%s] failed: %s {%s}',
                    $this->table,
                    $logId,
                    $e->getMessage(),
                    print_r($this->getProperties(), 1)
                )
            );
        }
        $this->modifiedProperties = array();
        $this->hasBeenModified = false;
        $this->onStore();
        $this->loadedFromDb = true;
        return $result;
    }


    /**
     * Delete item from DB
     *
     * @return int  Affected rows
     */
    protected function deleteFromDb()
    {
        return $this->db->delete(
            $this->table,
            $this->createWhere()
        );
    }

    protected function setKey($key)
    {
        $keyname = $this->getKeyName();
        if (is_array($keyname)) {
            foreach ($keyname as $k) {
                $this->set($k, $key[$k]);
            }
        } else {
            $this->set($keyname, $key);
        }
        return $this;
    }

    protected function existsInDb()
    {
        $result = $this->db->fetchRow(
            $this->db->select()->from($this->table)->where($this->createWhere())
        );
        return $result !== false;
    }

    protected function createWhere()
    {
        $key = $this->getKeyName();
        if (is_array($key) && ! empty($key)) {
            $where = array();
            foreach ($key as $k) {
                if ($this->hasBeenLoadedFromDb()) {
                    $where[] = $this->db->quoteInto(
                        sprintf('%s = ?', $k),
                        $this->loadedProperties[$k]
                    );
                } else {
                    $where[] = $this->db->quoteInto(
                        sprintf('%s = ?', $k),
                        $this->properties[$k]
                    );
                }
            }
            return implode(' AND ', $where);
        } else {
            return $this->db->quoteInto(
                sprintf('%s = ?', $key),
                $this->properties[$key]
            );
        }
    }

    public function delete()
    {
        $table = $this->table;
        $id = $this->getId();
        if (! $this->hasBeenLoadedFromDb() || ! $this->existsInDb()) {
            throw new Exception(sprintf('Cannot delete %s "%s" from Db', $table, $id));
        }
        $this->beforeDelete();
        if (! $this->deleteFromDb()) {
            throw new Exception(sprintf('Deleting %s (%s) FAILED', $table, $id));
        }
        // $this->log(sprintf('%s "%s" has been DELETED', $table, $id));
        $this->onDelete();
        $this->loadedFromDb = false;
        return true;
    }

    public function __clone()
    {
        $this->onClone();
        $this->autoincKeyName  = null;
        $this->loadedFromDb    = false;
        $this->hasBeenModified = true;
    }

    protected function onClone()
    {
    }

    public static function create($properties, DbConnection $connection = null)
    {
        $class = get_called_class();
        $obj = new $class();
        if ($connection !== null) {
            $obj->setConnection($connection);
        }
        $obj->setProperties($properties);
        return $obj;
    }

    public static function load($id, DbConnection $connection)
    {
        $class = get_called_class();
        $obj = new $class();
        $obj->setConnection($connection)->setKey($id)->loadFromDb();
        return $obj;
    }

    public static function loadAll(DbConnection $connection, $query = null, $keyColumn = null)
    {
        $objects = array();
        $class = get_called_class();
        $db = $connection->getDbAdapter();

        if ($query === null) {
            $dummy = new $class();
            $select = $db->select()->from($dummy->table);
        } else {
            $select = $query;
        }
        $rows = $db->fetchAll($select);

        foreach ($rows as $row) {
            $obj = new $class();
            $obj->setConnection($connection)->setDbProperties($row);
            if ($keyColumn === null) {
                $objects[] = $obj;
            } else {
                $objects[$row->$keyColumn] = $obj;
            }
        }

        return $objects;
    }

    public static function exists($id, DbConnection $connection)
    {
        $class = get_called_class();
        $obj = new $class();
        $obj->setConnection($connection)->setKey($id);
        return $obj->existsInDb();
    }
}
