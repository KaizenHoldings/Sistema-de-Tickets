<?php
namespace Controllers;

use Config\Database;
use PDO;
use Exception;

class UserController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Obtener listado de colaboradores/usuarios pertenecientes a un área para la asignación de tickets
     */
    public function getAgentsByArea(int $areaId): array {
        try {
            // Si el areaId es 0 o inválido, obtener el área por defecto
            if ($areaId <= 0) {
                $areaId = 1;
            }

            $stmt = $this->db->prepare("
                SELECT u.id, u.name, u.email, r.name as role_name, r.description as role_description
                FROM users u
                INNER JOIN roles r ON u.role_id = r.id
                WHERE (u.area_id = :area_id OR r.name = 'admin')
                  AND u.is_active = 1
                ORDER BY u.name ASC
            ");
            $stmt->execute([':area_id' => $areaId]);
            $users = $stmt->fetchAll();

            return ['success' => true, 'data' => $users];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Listar todos los usuarios
     */
    public function getAllUsers(): array {
        try {
            $stmt = $this->db->query("
                SELECT u.id, u.name, u.email, u.is_active, u.created_at, u.role_id, u.area_id,
                       r.name as role_name, r.description as role_description,
                       a.name as area_name
                FROM users u
                INNER JOIN roles r ON u.role_id = r.id
                LEFT JOIN areas a ON u.area_id = a.id
                ORDER BY u.id ASC
            ");
            return ['success' => true, 'data' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Crear un nuevo usuario
     */
    public function createUser(array $data, array $currentUser): array {
        try {
            if ($currentUser['role_name'] !== 'admin') {
                return ['success' => false, 'message' => 'Solo el Superadministrador puede registrar nuevos colaboradores.'];
            }

            $name = trim($data['name'] ?? '');
            $email = trim($data['email'] ?? '');
            $password = $data['password'] ?? 'password123';
            $roleId = (int)($data['role_id'] ?? 3);
            $areaId = !empty($data['area_id']) ? (int)$data['area_id'] : null;

            if (empty($name) || empty($email)) {
                return ['success' => false, 'message' => 'Nombre y correo electrónico son obligatorios.'];
            }

            $stmtCheck = $this->db->prepare("SELECT id FROM users WHERE email = :email");
            $stmtCheck->execute([':email' => $email]);
            if ($stmtCheck->fetch()) {
                return ['success' => false, 'message' => 'El correo electrónico ya existe.'];
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $this->db->prepare("
                INSERT INTO users (name, email, password_hash, role_id, area_id, is_active)
                VALUES (:name, :email, :password_hash, :role_id, :area_id, 1)
            ");
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password_hash' => $passwordHash,
                ':role_id' => $roleId,
                ':area_id' => $areaId
            ]);

            return ['success' => true, 'message' => "Usuario {$name} registrado exitosamente."];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Cambiar Rol de Usuario
     */
    public function toggleUserRole(int $targetUserId, int $newRoleId, array $currentUser): array {
        try {
            if ($currentUser['role_name'] !== 'admin') {
                return ['success' => false, 'message' => 'Solo el Superadministrador puede modificar roles.'];
            }

            $stmt = $this->db->prepare("UPDATE users SET role_id = :role_id WHERE id = :user_id");
            $stmt->execute([':role_id' => $newRoleId, ':user_id' => $targetUserId]);

            return ['success' => true, 'message' => 'Rol de usuario actualizado correctamente.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
