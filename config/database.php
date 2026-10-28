<?php

declare(strict_types=1);

const DB_HOST = 'db'; 
const DB_PORT = 3306;
const DB_NAME = 'db_rekomendasi_pembelajaran';
const DB_USER = 'root';
const DB_PASS = 'rahasia';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: DB_HOST;
    $port = (int) (getenv('DB_PORT') ?: DB_PORT);
    $name = getenv('DB_NAME') ?: DB_NAME;
    $user = getenv('DB_USER') ?: DB_USER;
    $pass = getenv('DB_PASS');
    $pass = $pass === false ? DB_PASS : $pass;

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        exit('<h1>Koneksi database gagal</h1><p>Periksa konfigurasi pada <code>config/database.php</code>.</p>');
    }

    return $pdo;
}
