# APTARS - Academic Performance Tracking, Analysis and Reporting System

PHP 8 + MySQL + Bootstrap 5 + Chart.js. Built for XAMPP.

## Requirements
- XAMPP (PHP 8.0+, MySQL 8 / MariaDB 10.4+)
- Modern browser

## Install
1. Place this folder at `C:\xampp2\htdocs\aptars`.
2. Start **Apache** and **MySQL** from the XAMPP2 control panel.
3. Open `http://localhost/phpmyadmin`.
4. Import `database/aptars.sql` (creates `aptars_db` and seeds demo data).
5. Verify `config/database.php` matches your environment (defaults: root / no password).
6. Visit `http://localhost/aptars/`.

## Demo accounts (password: `password`)
| Role     | Username   |
|----------|------------|
| Admin    | admin      |
| Lecturer | lecturer1  |
| Lecturer | lecturer2  |
| Student  | student1 ... student5 |

## Current Phase
Phase 1 - Foundation (auth, RBAC, dashboards, shared UI, database schema).