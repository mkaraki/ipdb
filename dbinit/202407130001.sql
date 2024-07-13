ALTER TABLE atkIps
    ADD asn bigint,
    ADD ccode varchar(2);

UPDATE db_scheme_version SET version = 202407130001 WHERE id = 1;