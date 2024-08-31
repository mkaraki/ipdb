CREATE TABLE atkDbIgnoreList(
    id BIGSERIAL PRIMARY KEY,
    net cidr NOT NULL,
    description TEXT
);
