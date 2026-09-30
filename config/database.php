<?php

class DatabaseUnavailableException extends RuntimeException
{
}

class Database
{
    private static ?PDO $connection = null;
    private static ?DatabaseUnavailableException $failure = null;

    public static function connect(): PDO
    {
        if (self::$failure !== null) {
            throw self::$failure;
        }

        if (self::$connection === null) {
            $host = getenv('BM_DB_HOST') ?: 'localhost';
            $name = getenv('BM_DB_NAME') ?: 'basta_masarap';
            $user = getenv('BM_DB_USER') ?: 'root';
            $pass = getenv('BM_DB_PASS') ?: '';

            try {
                self::$connection = new PDO(
                    "mysql:host={$host};dbname={$name};charset=utf8mb4",
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_TIMEOUT => 5,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '" . date('P') . "'",
                    ]
                );
            } catch (PDOException $e) {
                self::$failure = new DatabaseUnavailableException('Database connection failed: ' . $e->getMessage(), 0, $e);
                throw self::$failure;
            }
        }

        return self::$connection;
    }
}
