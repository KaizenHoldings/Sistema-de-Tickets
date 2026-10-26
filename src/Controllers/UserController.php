<?php
namespace Controllers;

use Config\Database;
use Config\Env;
use PDO;
use Exception;

class UserController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Sincronizar y asegurar la existencia del Superadministrador definido en las variables de entorno (.env)
     */
    public static function ensureSuperAdminExists(): void {
        try {
            $db = Database::getConnection();
            $email = strtolower(trim(Env::get('SUPERADMIN_EMAIL', 'superadmin@empresa.com')));
            $password = Env::get('SUPERADMIN_PASSWORD', 'password123');
            $name = trim(Env::get('SUPERADMIN_NAME', 'SuperAdmin Sistema'));

            if (empty($email)) {
                return;
            }

            // Asegurar que el rol 'admin' exista con ID 1
            $db->exec("INSERT IGNORE INTO roles (id, name, description) VALUES (1, 'admin', 'Superadministrador')");

            // Buscar si ya existe el usuario por su correo
            $stmt = $db->prepare("SELECT id, email, password_hash, role_id, is_active FROM users WHERE LOWER(email) = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            if ($user) {
                // Actualizar y asegurar que sea rol admin y esté activo
                $stmtUpdate = $db->prepare("
                    UPDATE users 
                    SET name = :name, role_id = 1, is_active = 1, password_hash = :password_hash 
                    WHERE id = :id
                ");
                $stmtUpdate->execute([
                    ':name' => $name,
                    ':password_hash' => $passwordHash,
                    ':id' => $user['id']
                ]);
            } else {
                // Si no existe por email, verificar si existe el usuario con id = 1
                $stmtId1 = $db->query("SELECT id, role_id FROM users WHERE id = 1 LIMIT 1");
                $user1 = $stmtId1->fetch();

                if ($user1 && (int)$user1['role_id'] === 1) {
                    $stmtUp1 = $db->prepare("
                        UPDATE users 
                        SET name = :name, email = :email, password_hash = :password_hash, is_active = 1 
                        WHERE id = 1
                    ");
                    $stmtUp1->execute([
                        ':name' => $name,
                        ':email' => $email,
                        ':password_hash' => $passwordHash
                    ]);
                } else {
                    $stmtInsert = $db->prepare("
                        INSERT INTO users (name, email, password_hash, role_id, area_id, is_active)
                        VALUES (:name, :email, :password_hash, 1, NULL, 1)
                    ");
                    $stmtInsert->execute([
                        ':name' => $name,
                        ':email' => $email,
                        ':password_hash' => $passwordHash
                    ]);
                }
            }
        } catch (\Throwable $e) {
            error_log("Error sincronizando Superadmin desde .env: " . $e->getMessage());
        }
    }

    /**
     * Obtener listado de colaboradores/usuarios pertenecientes a un área para la asignación de tickets
     */
    public function getAgentsByArea(int $areaId): array {
        try {
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
     * Listar todos los usuarios con conteo de actividad
     */
    public function getAllUsers(): array {
        try {
            $stmt = $this->db->query("
                SELECT u.id, u.name, u.email, u.is_active, u.created_at, u.role_id, u.area_id,
                       r.name as role_name, r.description as role_description,
                       a.name as area_name,
                       (SELECT COUNT(*) FROM tickets t WHERE t.assigned_agent_id = u.id) as assigned_tickets_count,
                       (SELECT COUNT(*) FROM tickets t WHERE t.requester_id = u.id) as created_tickets_count
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
     * Obtener un usuario por ID
     */
    public function getUserById(int $id): array {
        try {
            $stmt = $this->db->prepare("
                SELECT u.id, u.name, u.email, u.is_active, u.created_at, u.role_id, u.area_id,
                       r.name as role_name, r.description as role_description,
                       a.name as area_name
                FROM users u
                INNER JOIN roles r ON u.role_id = r.id
                LEFT JOIN areas a ON u.area_id = a.id
                WHERE u.id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'message' => 'Usuario no encontrado.'];
            }

            return ['success' => true, 'data' => $user];
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
            $email = strtolower(trim($data['email'] ?? ''));
            $password = $data['password'] ?? 'password123';
            $roleId = (int)($data['role_id'] ?? 3);
            $areaId = !empty($data['area_id']) ? (int)$data['area_id'] : null;

            if (empty($name) || empty($email)) {
                return ['success' => false, 'message' => 'Nombre y correo electrónico son obligatorios.'];
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'El formato del correo electrónico no es válido.'];
            }

            $stmtCheck = $this->db->prepare("SELECT id FROM users WHERE LOWER(email) = :email");
            $stmtCheck->execute([':email' => $email]);
            if ($stmtCheck->fetch()) {
                return ['success' => false, 'message' => 'El correo electrónico ya se encuentra registrado.'];
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

            return ['success' => true, 'message' => "Usuario {$name} registrado exitosamente.", 'id' => (int)$this->db->lastInsertId()];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Modificar un usuario existente (nombre, email, departamento, rol, y contraseña opcional)
     */
    public function updateUser(int $userId, array $data, array $currentUser): array {
        try {
            if ($currentUser['role_name'] !== 'admin') {
                return ['success' => false, 'message' => 'Solo el Superadministrador puede modificar usuarios.'];
            }

            $stmtUser = $this->db->prepare("SELECT id, role_id, email FROM users WHERE id = :id");
            $stmtUser->execute([':id' => $userId]);
            $targetUser = $stmtUser->fetch();

            if (!$targetUser) {
                return ['success' => false, 'message' => 'El usuario que intenta modificar no existe.'];
            }

            $name = trim($data['name'] ?? '');
            $email = strtolower(trim($data['email'] ?? ''));
            $roleId = isset($data['role_id']) ? (int)$data['role_id'] : (int)$targetUser['role_id'];
            $areaId = !empty($data['area_id']) ? (int)$data['area_id'] : null;

            if (empty($name) || empty($email)) {
                return ['success' => false, 'message' => 'Nombre y correo electrónico son obligatorios.'];
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'El formato del correo electrónico no es válido.'];
            }

            // Evitar que el superadministrador actual se quite a sí mismo el rol de superadmin
            if ($userId === (int)$currentUser['id'] && $roleId !== 1) {
                return ['success' => false, 'message' => 'No puedes cambiar tu propio rol de Superadministrador para evitar perder acceso.'];
            }

            // Verificar si el correo ya existe en otro usuario
            $stmtCheck = $this->db->prepare("SELECT id FROM users WHERE LOWER(email) = :email AND id != :id");
            $stmtCheck->execute([':email' => $email, ':id' => $userId]);
            if ($stmtCheck->fetch()) {
                return ['success' => false, 'message' => 'El correo electrónico ya pertenece a otro usuario registrado.'];
            }

            // Actualizar campos básicos
            $fields = "name = :name, email = :email, role_id = :role_id, area_id = :area_id";
            $params = [
                ':name' => $name,
                ':email' => $email,
                ':role_id' => $roleId,
                ':area_id' => $areaId,
                ':id' => $userId
            ];

            // Si se suministró una nueva contraseña
            if (!empty($data['password'])) {
                if (strlen($data['password']) < 6) {
                    return ['success' => false, 'message' => 'La nueva contraseña debe tener al menos 6 caracteres.'];
                }
                $fields .= ", password_hash = :password_hash";
                $params[':password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            $stmt = $this->db->prepare("UPDATE users SET {$fields} WHERE id = :id");
            $stmt->execute($params);

            return ['success' => true, 'message' => "Usuario {$name} actualizado correctamente."];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Alternar estado Activo / Inactivo del usuario
     */
    public function toggleUserStatus(int $targetUserId, array $currentUser): array {
        try {
            if ($currentUser['role_name'] !== 'admin') {
                return ['success' => false, 'message' => 'Solo el Superadministrador puede cambiar el estado de usuarios.'];
            }

            if ($targetUserId === (int)$currentUser['id']) {
                return ['success' => false, 'message' => 'No puedes desactivar tu propia cuenta mientras tienes la sesión activa.'];
            }

            $stmt = $this->db->prepare("SELECT id, name, is_active FROM users WHERE id = :id");
            $stmt->execute([':id' => $targetUserId]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'message' => 'El usuario especificado no existe.'];
            }

            $newStatus = ((int)$user['is_active'] === 1) ? 0 : 1;
            $stmtUpdate = $this->db->prepare("UPDATE users SET is_active = :status WHERE id = :id");
            $stmtUpdate->execute([':status' => $newStatus, ':id' => $targetUserId]);

            $statusText = $newStatus === 1 ? 'activado' : 'desactivado';
            return [
                'success' => true,
                'message' => "Usuario {$user['name']} {$statusText} correctamente.",
                'is_active' => $newStatus
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Restablecer contraseña de un usuario
     */
    public function resetPassword(int $targetUserId, string $newPassword, array $currentUser): array {
        try {
            if ($currentUser['role_name'] !== 'admin') {
                return ['success' => false, 'message' => 'Solo el Superadministrador puede restablecer contraseñas.'];
            }

            if (strlen($newPassword) < 6) {
                return ['success' => false, 'message' => 'La contraseña debe tener un mínimo de 6 caracteres.'];
            }

            $stmt = $this->db->prepare("SELECT id, name FROM users WHERE id = :id");
            $stmt->execute([':id' => $targetUserId]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'message' => 'Usuario no encontrado.'];
            }

            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmtUpdate = $this->db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            $stmtUpdate->execute([':hash' => $passwordHash, ':id' => $targetUserId]);

            return ['success' => true, 'message' => "Contraseña restablecida exitosamente para {$user['name']}."];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Eliminar usuario con validación de restricciones e integridad referencial
     */
    public function deleteUser(int $targetUserId, array $currentUser): array {
        try {
            if ($currentUser['role_name'] !== 'admin') {
                return ['success' => false, 'message' => 'Solo el Superadministrador puede eliminar usuarios.'];
            }

            if ($targetUserId === (int)$currentUser['id']) {
                return ['success' => false, 'message' => 'No puedes eliminar tu propia cuenta de usuario.'];
            }

            $stmt = $this->db->prepare("SELECT id, name, role_id FROM users WHERE id = :id");
            $stmt->execute([':id' => $targetUserId]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'message' => 'El usuario a eliminar no existe.'];
            }

            // Verificar si es superadmin y si es el único superadmin activo
            if ((int)$user['role_id'] === 1) {
                $countAdmin = (int)$this->db->query("SELECT COUNT(*) FROM users WHERE role_id = 1 AND is_active = 1")->fetchColumn();
                if ($countAdmin <= 1) {
                    return ['success' => false, 'message' => 'No se puede eliminar el único Superadministrador del sistema.'];
                }
            }

            // Validar si tiene tickets creados
            $stmtReq = $this->db->prepare("SELECT COUNT(*) FROM tickets WHERE requester_id = :id");
            $stmtReq->execute([':id' => $targetUserId]);
            $createdTickets = (int)$stmtReq->fetchColumn();

            // Validar si tiene tickets asignados
            $stmtAss = $this->db->prepare("SELECT COUNT(*) FROM tickets WHERE assigned_agent_id = :id");
            $stmtAss->execute([':id' => $targetUserId]);
            $assignedTickets = (int)$stmtAss->fetchColumn();

            // Validar si tiene comentarios
            $stmtCom = $this->db->prepare("SELECT COUNT(*) FROM ticket_comments WHERE user_id = :id");
            $stmtCom->execute([':id' => $targetUserId]);
            $commentsCount = (int)$stmtCom->fetchColumn();

            if ($createdTickets > 0 || $assignedTickets > 0 || $commentsCount > 0) {
                return [
                    'success' => false,
                    'message' => "No se puede eliminar permanentemente al usuario '{$user['name']}' porque cuenta con {$createdTickets} tickets creados, {$assignedTickets} tickets asignados y/o {$commentsCount} comentarios registrados. Se recomienda DESACTIVAR su cuenta para revocar su acceso sin alterar la trazabilidad del sistema."
                ];
            }

            // Desvincular áreas gestionadas por este usuario
            $this->db->prepare("UPDATE areas SET manager_id = NULL WHERE manager_id = :id")->execute([':id' => $targetUserId]);

            // Desvincular tickets asignados a este usuario
            $this->db->prepare("UPDATE tickets SET assigned_agent_id = NULL WHERE assigned_agent_id = :id")->execute([':id' => $targetUserId]);

            // Proceder con la eliminación física
            $stmtDel = $this->db->prepare("DELETE FROM users WHERE id = :id");
            $stmtDel->execute([':id' => $targetUserId]);

            return ['success' => true, 'message' => "Usuario '{$user['name']}' eliminado exitosamente."];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error al eliminar usuario: ' . $e->getMessage()];
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

            if ($targetUserId === (int)$currentUser['id'] && $newRoleId !== 1) {
                return ['success' => false, 'message' => 'No puedes remover tu propio rol de Superadministrador.'];
            }

            $stmt = $this->db->prepare("UPDATE users SET role_id = :role_id WHERE id = :user_id");
            $stmt->execute([':role_id' => $newRoleId, ':user_id' => $targetUserId]);

            return ['success' => true, 'message' => 'Rol de usuario actualizado correctamente.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
