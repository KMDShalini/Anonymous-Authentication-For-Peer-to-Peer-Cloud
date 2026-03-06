-- ========================================
-- Database: AppDB
-- Tables: admins, app_users, encrypted_files, transfer_requests
-- ========================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS AppDB;
USE AppDB;

-- ========================================
-- Table: admins
-- ========================================
CREATE TABLE IF NOT EXISTS admins (
    id INT NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- Table: app_users
-- ========================================
CREATE TABLE IF NOT EXISTS app_users (
    id INT NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    alias VARCHAR(100) DEFAULT NULL,
    anon_id VARCHAR(255) NOT NULL UNIQUE,
    cloud ENUM('A','B') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- Table: encrypted_files
-- ========================================
CREATE TABLE IF NOT EXISTS encrypted_files (
    id INT NOT NULL AUTO_INCREMENT,
    transfer_request_id INT NOT NULL,
    sender_anon_id VARCHAR(255) NOT NULL,
    receiver_anon_id VARCHAR(255) NOT NULL,
    encrypted_blob LONGBLOB NOT NULL,
    original_hash VARCHAR(64) NOT NULL,
    decrypted_hash VARCHAR(255) DEFAULT NULL,
    original_filename VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','approved','rejected','downloaded') NOT NULL DEFAULT 'pending',
    PRIMARY KEY (id),
    KEY transfer_request_id_idx (transfer_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- Table: transfer_requests
-- ========================================
CREATE TABLE IF NOT EXISTS transfer_requests (
    id INT NOT NULL AUTO_INCREMENT,
    sender_username VARCHAR(100) NOT NULL,
    sender_alias_name VARCHAR(100) DEFAULT NULL,
    receiver_username VARCHAR(100) NOT NULL,
    sender_anon_id VARCHAR(255) NOT NULL,
    receiver_anon_id VARCHAR(255) NOT NULL,
    sender_public_key TEXT NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    receiver_public_key TEXT DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;