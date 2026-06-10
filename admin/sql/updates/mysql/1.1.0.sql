CREATE TABLE IF NOT EXISTS `#__mcpserver_request_log` (
  `id`          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `created`     DATETIME NOT NULL,
  `method`      VARCHAR(64)  NOT NULL DEFAULT '',
  `tool_name`   VARCHAR(128) NOT NULL DEFAULT '',
  `status`      VARCHAR(20)  NOT NULL DEFAULT '',
  `error_code`  INT(11)      NULL DEFAULT NULL,
  `http_status` SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
  `duration_ms` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `client_ip`   VARCHAR(45)  NOT NULL DEFAULT '',
  `context`     VARCHAR(10)  NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created`),
  KEY `idx_method` (`method`),
  KEY `idx_tool` (`tool_name`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
