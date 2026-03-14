-- Create both databases on MySQL startup.
-- mysql service sets MYSQL_DATABASE=wordpress_test (used by wp-test PHPUnit suite).
-- The wordpress staging service uses a separate 'wordpress' database.
CREATE DATABASE IF NOT EXISTS `wordpress`;
