ALTER TABLE trap MODIFY short_name VARCHAR(64) NULL DEFAULT NULL;
ALTER TABLE trap MODIFY mib_name VARCHAR(64) NULL DEFAULT NULL;
ALTER TABLE trap MODIFY timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE trap ADD COLUMN acknowledged ENUM('y', 'n') NOT NULL DEFAULT 'n' AFTER short_name;
ALTER TABLE trap ADD INDEX idx_host_ack (host_name(64), acknowledged);
ALTER TABLE trap ADD INDEX idx_ts_ack (timestamp, acknowledged);

UPDATE trap t
  JOIN trap_oidcache c ON t.oid = c.oid
  SET
    t.mib_name = c.mib_name,
    t.short_name = c.short_name,
    t.message = c.description
  WHERE (t.short_name LIKE '%.%.%' OR t.short_name IS NULL)
    AND c.short_name NOT LIKE '%.%.%';

ALTER TABLE trap
  ADD COLUMN oid_checksum VARBINARY(20) NOT NULL AFTER messageid,
  ADD INDEX oid_checksum (oid_checksum);
-- Query OK, 35536 rows affected (1.73 sec)

UPDATE trap SET oid_checksum = UNHEX(SHA1(oid));
-- Query OK, 35536 rows affected (1.31 sec)

ALTER TABLE trap_varbind
  ADD COLUMN oid_checksum VARBINARY(20) NOT NULL AFTER trap_id,
  ADD INDEX oid_checksum (oid_checksum);
-- Query OK, 1228915 rows affected (18.07 sec)

UPDATE trap_varbind SET oid_checksum = UNHEX(SHA1(oid));
-- Query OK, 1228915 rows affected (1 min 32.57 sec)

ALTER TABLE trap_oidcache
  ADD COLUMN oid_checksum VARBINARY(20) NOT NULL AFTER oid,
  ADD INDEX oid_checksum (oid_checksum);

UPDATE trap_oidcache SET oid_checksum = UNHEX(SHA1(oid));
