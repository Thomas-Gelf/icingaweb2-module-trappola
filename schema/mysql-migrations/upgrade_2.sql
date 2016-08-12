CREATE TABLE icinga_trap_issue (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  checksum VARBINARY(20) NOT NULL,
  icinga_object VARCHAR(255) DEFAULT NULL,
  icinga_object_checksum VARBINARY(20) NOT NULL,
  icinga_host VARCHAR(255) NOT NULL,
  icinga_service VARCHAR(255) NOT NULL,
  first_event DATETIME NOT NULL,
  last_event DATETIME NOT NULL,
  cnt_events INT(10) UNSIGNED NOT NULL,
  icinga_state TINYINT UNSIGNED NOT NULL,
  message TEXT DEFAULT NULL,
  expire_after DATETIME DEFAULT NULL,
  PRIMARY KEY(id),
  UNIQUE KEY(checksum),
  KEY icinga_object_checksum (icinga_object_checksum),
  KEY icinga_host (icinga_host)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

