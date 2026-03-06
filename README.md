# Anonymous-Authentication-For-Peer-to-Peer-Cloud


## Overview
This project demonstrates secure cloud-to-cloud communication using **ECC, ECDH, AES-256**, and **anonymous identities**.  
It involves three servers: **App Server**, **Cloud A Server**, and **Cloud B Server**, deployed on **AWS EC2**.

---

## 1. Software Requirements

### 1.1 Cloud Platform
- **Cloud Service Provider:** Amazon Web Services (AWS)  
- **Service Used:** Amazon EC2 (Elastic Compute Cloud)  
- **Instance Type:** t3.micro (Free Tier Eligible)  
- **Operating System:** Ubuntu 22.04 LTS  

> EC2 instances are used to host the App Server, Cloud A, and Cloud B servers. Ubuntu 22.04 LTS ensures stability and compatibility with Apache, PHP, and MySQL.

### 1.2 Server Software
- **Web Server:** Apache2  
- **Server-side Language:** PHP  
- **Database Server:** MySQL  
- **Secure Remote Access:** OpenSSH  

> Apache2 handles HTTP requests. PHP implements application logic. MySQL stores user data, encrypted files, and request logs. OpenSSH provides secure remote access.

### 1.3 Development Tools
- **Code Editor:** Visual Studio Code  
- **Web Browsers:** Google Chrome / Firefox  
- **Terminal:** Windows Command Prompt / PowerShell / Linux Terminal  

### 1.4 Cryptographic Components
- **ECC (Elliptic Curve Cryptography)** – for key generation  
- **ECDH (Elliptic Curve Diffie–Hellman)** – for secure key exchange  
- **AES-256 Encryption** – for encrypting files  
- **SHA-256 Hash Function** – for generating anonymous IDs  
- **Secure Random Byte Generator** – for private keys and IDs  

### 1.5 Network Requirements
- Each EC2 instance must have a **public IPv4 address**  
- Security groups must allow:
  - **SSH (Port 22)**  
  - **HTTP (Port 80)**  
  - **MySQL (Port 3306)** – restricted access  
- Stable internet connectivity is required

---

## 2. Hardware Requirements

| Server | Instance Type | vCPU | RAM | Storage | Role |
|--------|---------------|------|-----|--------|------|
| App Server | t3.micro | 2 | 1 GB | 8 GB EBS | Hosts web app, app_db, handles ECC/ECDH/AES, controls cloud communication |
| Cloud A Server | t3.micro | 2 | 1 GB | 8 GB EBS | Hosts cloudA_db, participates in ECC/ECDH/AES operations |
| Cloud B Server | t3.micro | 2 | 1 GB | 8 GB EBS | Hosts cloudB_db, participates in ECC/ECDH/AES operations |

> Each server has an **Elastic IP** for stable connectivity.

---

## 3. Project Folder Structure
```bash
repo-root/
├─ majorProject/
│   ├─ index.html
│   ├─ css/
│   ├─ js/
│   ├─ php/
├─ sql/
│   ├─ app_db.sql
│   ├─ cloudA_db.sql
│   ├─ cloudB_db.sql
├─ README.md
```

---

## 4. Database Setup

### 1. Import databases using MySQL:

```bash
mysql -u root -p < sql/app_db.sql
mysql -u root -p < sql/cloudA_db.sql
mysql -u root -p < sql/cloudB_db.sql
```
Update db_connection.php with Elastic IPs and credentials.

## 5. Server Access & Testing
### 5.1 App Server
```bash
URL: http://<App-Elastic-IP>/majorProject/home.html
```

SSH:
```bash
ssh -i AppKey.pem ubuntu@<App-Elastic-IP>
```

MySQL commands:
```bash
USE app_db;
SHOW TABLES;
SELECT * FROM app_users;
SELECT 
    id,
    transfer_request_id,
    sender_anon_id,
    receiver_anon_id,
    original_filename,
    original_hash,	
    decrypted_hash,
    status,
    created_at
FROM encrypted_files;
```

### 5.2 Cloud A Server
```bash
cd Downloads
ssh -i cloudAkey.pem ubuntu@<CloudA-Elastic-IP>
sudo mysql;
USE cloudA_db;
SELECT id, owner_anon_id, file_name, uploaded_at FROM cloud_files;
```

### 5.3 Cloud B Server
```bash
ssh -i cloudB-key.pem ubuntu@<CloudB-Elastic-IP>
SELECT id, owner_anon_id, sender_anon_id, original_filename, file_hash, received_at, downloaded FROM received_files;
```

## 6. Apache & PHP Configuration

Navigate to project directory:
```bash
  cd /var/www/html/majorProject
  sudo nano login.php
```

## 7. How to Execute the Project

Set up databases (see Section 4)

Update db_connection.php with Elastic IPs and DB credentials

Upload files to server 
```bash
/var/www/html/majorProject
```
Access via browser using App Server Elastic IP

Test file encryption, transfer, and decryption between Cloud A and Cloud B

## 8. Notes

Ensure Elastic IPs are used to avoid connectivity issues

Security groups must allow SSH, HTTP, and restricted MySQL access

Use provided SSH keys for server access

Test each component individually: App Server, Cloud A, Cloud B

## 9. References

AWS EC2 Free Tier Documentation: https://aws.amazon.com/free

Ubuntu 22.04 LTS Documentation: https://ubuntu.com/22.04

PHP Documentation: https://www.php.net/docs.php

MySQL Documentation: https://dev.mysql.com/doc/




