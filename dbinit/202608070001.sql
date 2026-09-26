ALTER TABLE atkIps
    ADD COLUMN ip_bin VARBINARY(16)
    GENERATED ALWAYS AS (INET6_ATON(ip)) STORED,
    ADD COLUMN ip_prefix24 VARBINARY(15)
    GENERATED ALWAYS AS (LEFT(INET6_ATON(ip), 15)) STORED,
    ADD COLUMN ip_prefix64 VARBINARY(8)
    GENERATED ALWAYS AS (LEFT(INET6_ATON(ip), 8)) STORED;

CREATE INDEX idx_atkIps_prefix24
    ON atkIps (ip_prefix24);

CREATE INDEX idx_atkIps_prefix64
    ON atkIps (ip_prefix64);

CREATE INDEX idx_atkIps_lastseen
    ON atkIps(lastseen);

UPDATE db_schema_version SET version = 202608070001 WHERE id = 1;
