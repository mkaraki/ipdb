CREATE TABLE admin_users(
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(127) NOT NULL,
    password_hash VARCHAR(127) NOT NULL
);

CREATE TABLE atk_reporter_users(
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(128) NOT NULL,
    password_hash VARCHAR(128) NOT NULL,
    display_name TEXT
);

UPDATE db_schema_version SET version = 202610040001 WHERE id = 1;
