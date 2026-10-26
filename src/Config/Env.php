<?php
namespace Config;

class Env {
    private static ?array $envData = null;

    /**
     * Cargar variables de entorno desde el archivo .env si no están presentes
     */
    public static function load(): void {
        if (self::$envData !== null) {
            return;
        }

        self::$envData = [];
        $envPath = dirname(__DIR__, 2) . '/.env';

        if (file_exists($envPath) && is_readable($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $val = trim($parts[1]);

                    // Quitar comentarios inline si existen
                    if (preg_match('/^([^#]+)\s*#.*$/', $val, $matches)) {
                        $val = trim($matches[1]);
                    }

                    // Quitar comillas si están presentes
                    if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                        (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                        $val = substr($val, 1, -1);
                    }

                    self::$envData[$key] = $val;

                    if (getenv($key) === false) {
                        putenv("{$key}={$val}");
                    }
                    if (!isset($_ENV[$key])) {
                        $_ENV[$key] = $val;
                    }
                    if (!isset($_SERVER[$key])) {
                        $_SERVER[$key] = $val;
                    }
                }
            }
        }
    }

    /**
     * Obtener valor de variable de entorno con fallback
     */
    public static function get(string $key, ?string $default = null): ?string {
        self::load();

        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return $val;
        }

        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string)$_ENV[$key];
        }

        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return (string)$_SERVER[$key];
        }

        if (isset(self::$envData[$key]) && self::$envData[$key] !== '') {
            return (string)self::$envData[$key];
        }

        return $default;
    }
}
