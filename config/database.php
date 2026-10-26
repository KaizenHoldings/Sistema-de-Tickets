<?php
namespace Config;

require_once __DIR__ . '/../src/Config/Env.php';

use PDO;
use PDOException;

class Database {
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host = Env::get('DB_HOST', '127.0.0.1');
            $port = Env::get('DB_PORT', '3306');
            $db   = Env::get('DB_NAME', 'service_desk_db');
            $user = Env::get('DB_USER', 'root');
            $pass = Env::get('DB_PASS') !== null ? Env::get('DB_PASS') : (Env::get('DB_PASSWORD', 'root'));
            $charset = 'utf8mb4';

            $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                throw new PDOException("Error de Conexión a la Base de Datos: " . $e->getMessage(), (int)$e->getCode());
            }
        }
        return self::$instance;
    }
}
