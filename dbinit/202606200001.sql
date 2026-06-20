ALTER TABLE atkIps
    ADD is_frontend_attack BOOLEAN DEFAULT FALSE NOT NULL,
    ADD attack_count BIGINT UNSIGNED DEFAULT 1 NOT NULL;

UPDATE db_schema_version SET version = 202605200001 WHERE id = 1;