CREATE TABLE meta_rdns(
    id BIGSERIAL PRIMARY KEY,
    ip inet NOT NULL,
    rdns varchar(254) DEFAULT(NULL),
    last_checked timestamp NOT NULL
);

ALTER TABLE atkIps
    DROP rdns,
    DROP region,
    DROP ccode,
    DROP asn;

CREATE TABLE db_scheme_version(
    id INT PRIMARY KEY,
    version bigint NOT NULL
);

INSERT INTO db_scheme_version(id, version) VALUES(1, 202402120001);