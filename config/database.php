<?php
/**
 * APTARS — Database configuration & PDO singleton.
 * Modify the constants below to match your XAMPP setup.
 */

declare(strict_types=1);

// ---------- Application ----------
define('APP_NAME',    'APTARS');
define('APP_URL',     '/aptars');
define('APP_VERSION', '1.0.0-phase1');
define('APP_DEBUG', false);   // set to false before final deployment

// ---------- Database ----------
define('DB_HOST', 'localhost');
define('DB_NAME', 'aptars_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---------- Error handling ----------
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}
date_default_timezone_set('Africa/Nairobi');

/**
 * Return the shared PDO connection (singleton).
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        error_log('[APTARS DB ERROR] ' . $e->getMessage());
        http_response_code(500);
        exit('Database connection failed. Please check config/database.php.');
    }
    return $pdo;
}