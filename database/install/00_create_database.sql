-- ============================================================================
-- LMS  -  Step 00: create the database
--
-- Run this FIRST on a new server. Safe to run more than once
-- (IF NOT EXISTS), but it never alters an existing database.
--
--     mysql -u root < 00_create_database.sql
--     (Windows: C:\xampp\mysql\bin\mysql.exe -u root < 00_create_database.sql)
--
-- Then import, in order:
--     01_schema.sql            31 tables, structure only
--     02_seed_reference.sql    tbl_utility reference data
--     03_verify.sql            post-install checks
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `lms`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE `lms`;
