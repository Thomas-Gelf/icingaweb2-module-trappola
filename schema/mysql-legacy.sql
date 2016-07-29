CREATE TABLE trap (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  listener_id int(10) unsigned DEFAULT NULL,
  timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  host_name varchar(255) DEFAULT NULL,
  src_address varbinary(16) NOT NULL,
  src_port int(10) unsigned DEFAULT NULL,
  dst_address varbinary(16) NOT NULL,
  dst_port int(10) unsigned DEFAULT NULL,
  network_protocol enum('ipv4','ipv6') DEFAULT NULL,
  auth varchar(255) NOT NULL,
  message text,
  sys_uptime int(10) unsigned DEFAULT NULL,
  type enum('trap','trapv1','inform') NOT NULL,
  version enum('v1','v2c','v3') NOT NULL,
  requestid int(10) unsigned DEFAULT NULL,
  transactionid int(10) unsigned DEFAULT NULL,
  messageid int(10) unsigned DEFAULT NULL,
  oid varchar(1024) NOT NULL,
  short_name varchar(64) NOT NULL,
  mib_name varchar(64) NOT NULL,
  transport enum('udp') DEFAULT NULL,
  security enum('v1','v2c','usm') NOT NULL,
  v3_sec_level enum('noAuthNoPriv','authNoPriv','authPriv') DEFAULT NULL,
  v3_sec_name varchar(32) DEFAULT NULL,
  v3_sec_engine varbinary(32) DEFAULT NULL,
  v3_ctx_name varchar(32) DEFAULT NULL,
  v3_ctx_engine varbinary(64) DEFAULT NULL,
  KEY id (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='SNMP trap';

CREATE TABLE trap_action (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  description varchar(1024) NOT NULL,
  host varchar(256) NOT NULL,
  service varchar(128) NOT NULL,
  correlation varchar(256) NOT NULL,
  severity enum('CRITICAL','WARNING','OK','UNKNOWN') NOT NULL,
  clear enum('yes','no') DEFAULT 'no',
  is_last enum('yes','no') DEFAULT 'no',
  KEY id (id),
  KEY correlation (host(64),service(64),correlation(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='SNMP trap actions';

CREATE TABLE trap_filter (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  oid varchar(1024) NOT NULL,
  type enum('is','contains','regexp') DEFAULT NULL,
  KEY id (id),
  KEY oid (oid(256))
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='SNMP trap filters';

CREATE TABLE trap_oidcache (
  oid varchar(1024) NOT NULL,
  short_name varchar(128) NOT NULL,
  mib_name varchar(64) NOT NULL,
  description text,
  KEY oid (oid(128))
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE trap_rule (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  description varchar(1024) NOT NULL,
  filter_type enum('any','all') DEFAULT NULL,
  KEY id (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='SNMP trap rules';

CREATE TABLE trap_rule_action (
  rule_id int(10) unsigned DEFAULT NULL,
  action_id int(10) unsigned DEFAULT NULL,
  KEY ids (rule_id,action_id),
  KEY action_id (action_id),
  CONSTRAINT trap_rule_action_ibfk_1 FOREIGN KEY (rule_id) REFERENCES trap_rule (id),
  CONSTRAINT trap_rule_action_ibfk_2 FOREIGN KEY (action_id) REFERENCES trap_action (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='SNMP trap rule actions';

CREATE TABLE trap_rule_filter (
  rule_id int(10) unsigned DEFAULT NULL,
  filter_id int(10) unsigned DEFAULT NULL,
  KEY ids (rule_id,filter_id),
  KEY filter_id (filter_id),
  CONSTRAINT trap_rule_filter_ibfk_1 FOREIGN KEY (rule_id) REFERENCES trap_rule (id),
  CONSTRAINT trap_rule_filter_ibfk_2 FOREIGN KEY (filter_id) REFERENCES trap_filter (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='SNMP trap rule filters';

CREATE TABLE trap_varbind (
  trap_id bigint(20) unsigned NOT NULL,
  oid varchar(1024) NOT NULL,
  type enum('boolean','integer','bit_str','octet_str','null','object_id','application','sequence','set','ipaddress','counter','unsigned','timeticks','opaque','counter64','float','double','integer64','unsigned64','unknown') NOT NULL,
  value blob NOT NULL,
  KEY varbind (trap_id,oid(128)),
  CONSTRAINT trap_varbind_ibfk_1 FOREIGN KEY (trap_id) REFERENCES trap (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- TODO: add real migrations

alter table trap modify short_name varchar(64) null default null;
alter table trap modify mib_name varchar(64) null default null;
alter table trap modify timestamp timestamp not null default current_timestamp;

ALTER TABLE trap ADD COLUMN acknowledged ENUM('y', 'n') NOT NULL DEFAULT 'n' AFTER short_name;
ALTER TABLE trap ADD INDEX idx_host_ack (host_name(64), acknowledged);
ALTER TABLE trap ADD INDEX idx_ts_ack (timestamp, acknowledged);


UPDATE trap t JOIN trap_oidcache c ON t.oid = c.oid SET t.mib_name = c.mib_name, t.short_name = c.short_name, t.message = c.description WHERE (t.short_name LIKE '%.%.%' OR t.short_name IS NULL) AND c.short_name NOT LIKE '%.%.%'

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
