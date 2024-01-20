CREATE TABLE atkIps(
    id BIGSERIAL PRIMARY KEY,
    ip inet NOT NULL,
    rdns varchar(254) DEFAULT(NULL),
    ccode CHAR(2) DEFAULT (NULL),
    region VARCHAR(3) DEFAULT(NULL),
    asn bigint DEFAULT(NULL),
    addedat timestamp NOT NULL,
    lastseen timestamp DEFAULT(NULL)
);