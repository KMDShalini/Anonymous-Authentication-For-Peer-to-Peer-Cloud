-- ========================================
-- Database: cloudA_db
-- Tables: cloud_files, cloud_users, received_files
-- ========================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS cloudA_db;
USE cloudA_db;

-- ========================================
-- Table: cloud_files
-- ========================================
CREATE TABLE IF NOT EXISTS cloud_files (
    id INT NOT NULL AUTO_INCREMENT,
    owner_anon_id VARCHAR(64) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_data LONGBLOB NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- Table: cloud_users
-- ========================================
CREATE TABLE IF NOT EXISTS cloud_users (
    id INT NOT NULL AUTO_INCREMENT,
    anon_id VARCHAR(64) NOT NULL UNIQUE,
    ecc_private_key TEXT NOT NULL,
    ecc_public_key TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- Table: received_files
-- ========================================
CREATE TABLE IF NOT EXISTS received_files (
    id INT NOT NULL AUTO_INCREMENT,
    owner_anon_id VARCHAR(64) DEFAULT NULL,
    sender_anon_id VARCHAR(64) DEFAULT NULL,
    original_filename VARCHAR(255) DEFAULT NULL,
    file_data LONGBLOB DEFAULT NULL,
    file_hash VARCHAR(64) DEFAULT NULL,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    downloaded ENUM('no','yes') DEFAULT 'no',
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;