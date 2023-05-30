CREATE EXTENSION IF NOT EXISTS citext;
CREATE TABLE IF NOT EXISTS passwords(
    account_id SERIAL PRIMARY KEY,
    username CITEXT NOT NULL UNIQUE,
    pass CHAR(60) NOT NULL
);
CREATE TABLE IF NOT EXISTS emails(
    account_id INTEGER PRIMARY KEY,
    email CITEXT UNIQUE
);
CREATE TABLE IF NOT EXISTS content(
    task_id SERIAL PRIMARY KEY,
    task VARCHAR(256) NOT NULL,
    task_status CHAR(1) NOT NULL,
    account_id INTEGER NOT NULL,
    username CITEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS password_reset_tokens(
    account_id INTEGER PRIMARY KEY,
    token CHAR(30) NOT NULL UNIQUE,
    expiry TIMESTAMP NOT NULL
);
CREATE TABLE IF NOT EXISTS api_tokens(
    account_id INTEGER PRIMARY KEY,
    token CHAR(30) NOT NULL UNIQUE
);