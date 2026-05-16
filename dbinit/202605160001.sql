CREATE TABLE atkIps
(
    id       BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    ip INET6 NOT NULL,
    ccode    CHAR(2) CHARACTER SET latin1 COLLATE latin1_swedish_ci    DEFAULT (NULL),
    asn      bigint                                                    DEFAULT (NULL),
    addedat  timestamp NOT NULL,
    lastseen timestamp                                                 DEFAULT (NULL)
);

CREATE TABLE meta_rdns
(
    id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    ip INET6 NOT NULL,
    rdns         VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT (NULL),
    last_checked timestamp NOT NULL
);

CREATE TABLE atkDbIgnoreList
(
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    network INET6 NOT NULL,
    cidr INT NOT NULL,
    description TEXT
);

CREATE TABLE db_scheme_version
(
    id      TINYINT UNSIGNED PRIMARY KEY,
    version BIGINT UNSIGNED NOT NULL
);

INSERT INTO db_scheme_version(id, version)
VALUES (1, 202605160001);
