-- FreePBX VoIP Platform Database Setup
-- Run this script to create the database

CREATE DATABASE IF NOT EXISTS freepbx_voip_platform 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Create a dedicated user for the application (optional)
-- CREATE USER 'voip_user'@'localhost' IDENTIFIED BY 'secure_password';
-- GRANT ALL PRIVILEGES ON freepbx_voip_platform.* TO 'voip_user'@'localhost';
-- FLUSH PRIVILEGES;

USE freepbx_voip_platform;