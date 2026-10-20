<?php
namespace Middleware;

class AuthMiddleware {

    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            // Configurar opciones de cookies de sesión seguras
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_samesite', 'Lax');
            session_start();
        }

        // Generar token CSRF si no existe
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function getCsrfToken(): string {
        self::startSession();
        return $_SESSION['csrf_token'] ?? '';
    }

    public static function validateCsrfToken(?string $token): bool {
        self::startSession();
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function getCurrentUser(): ?array {
        self::startSession();
        return $_SESSION['user'] ?? null;
    }

    public static function requireAuth(): array {
        self::startSession();
        if (!isset($_SESSION['user'])) {
            if (self::isAjaxRequest()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'No autorizado. Debe iniciar sesión.']);
                exit;
            } else {
                header('Location: login.php');
                exit;
            }
        }
        return $_SESSION['user'];
    }

    public static function requireRoles(array $allowedRoles): array {
        $user = self::requireAuth();
        if (!in_array($user['role_name'], $allowedRoles, true)) {
            if (self::isAjaxRequest()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Acceso denegado. Permisos insuficientes.']);
                exit;
            } else {
                die('Error 403: No posee los permisos requeridos para acceder a este módulo.');
            }
        }
        return $user;
    }

    public static function isAjaxRequest(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ||
               strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    }
}
