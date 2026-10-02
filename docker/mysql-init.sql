-- A second database for the test suite, so tests never touch development data.
CREATE DATABASE IF NOT EXISTS helpdesk_test;
GRANT ALL PRIVILEGES ON helpdesk_test.* TO 'helpdesk'@'%';
