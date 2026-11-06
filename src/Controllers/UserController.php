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
                SELECT u.id, u.name, u.alias, u.email, u.is_active, u.created_at, u.role_id, u.area_id,
                       r.name as role_name, r.description as role_description,
                       a.name as area_name,
                       (SELECT COUNT(*) FROM tickets t WHERE t.assigned_agent_id = u.id OR (t.responsable IS NOT NULL AND TRIM(t.responsable) != '' AND (LOWER(TRIM(t.responsable)) = LOWER(u.alias) OR LOWER(TRIM(t.responsable)) = LOWER(u.name)))) as assigned_tickets_count,
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
                SELECT u.id, u.name, u.alias, u.email, u.is_active, u.created_at, u.role_id, u.area_id,
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
            $alias = trim($data['alias'] ?? '') ?: $name;
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
                INSERT INTO users (name, alias, email, password_hash, role_id, area_id, is_active)
                VALUES (:name, :alias, :email, :password_hash, :role_id, :area_id, 1)
            ");
            $stmt->execute([
                ':name' => $name,
                ':alias' => $alias,
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
     * Modificar un usuario existente (nombre, alias, email, departamento, rol, y contraseña opcional)
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
            $alias = trim($data['alias'] ?? '') ?: $name;
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
            $fields = "name = :name, alias = :alias, email = :email, role_id = :role_id, area_id = :area_id";
            $params = [
                ':name' => $name,
                ':alias' => $alias,
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

            // Si el usuario editado es el usuario en sesión, actualizar sesión
            if ($userId === (int)($currentUser['id'] ?? 0)) {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION['user']['name'] = $name;
                    $_SESSION['user']['alias'] = $alias;
                }
            }

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

    /**
     * Obtener estadísticas completas del perfil del usuario (conteo de tickets, tiempo promedio y dificultad)
     */
    public function getUserProfileStats(array $currentUser): array {
        try {
            $userId = (int)$currentUser['id'];
            $userName = trim($currentUser['name'] ?? '');
            $userEmail = trim($currentUser['email'] ?? '');

            // Obtener alias de la BD si no viene en sesión
            $stmtUserRow = $this->db->prepare("SELECT alias, name FROM users WHERE id = :uid");
            $stmtUserRow->execute([':uid' => $userId]);
            $uRow = $stmtUserRow->fetch(PDO::FETCH_ASSOC);
            $userAlias = trim($uRow['alias'] ?? $currentUser['alias'] ?? $userName);
            if (!empty($uRow['name'])) $userName = $uRow['name'];

            // 1. Obtener todos los tickets asignados a este usuario (por id o por alias/nombre en SharePoint)
            $stmtTickets = $this->db->prepare("
                SELECT t.id, t.code, t.title, t.priority, t.status_id, t.created_at, t.updated_at, 
                       t.due_date, t.is_sla_breached, t.resolved_at, t.sp_status, t.company_name, t.area_code,
                       ks.name as status_name, ks.slug as status_slug,
                       a.name as area_name
                FROM tickets t
                LEFT JOIN kanban_status ks ON t.status_id = ks.id
                LEFT JOIN areas a ON t.area_id = a.id
                WHERE t.assigned_agent_id = :uid 
                   OR (t.responsable IS NOT NULL AND TRIM(t.responsable) != '' AND (
                       LOWER(TRIM(t.responsable)) = LOWER(:uname) 
                       OR LOWER(TRIM(t.responsable)) = LOWER(:ualias)
                   ))
                ORDER BY t.created_at DESC
            ");
            $stmtTickets->execute([
                ':uid'    => $userId, 
                ':uname'  => $userName,
                ':ualias' => $userAlias
            ]);
            $assignedTickets = $stmtTickets->fetchAll(PDO::FETCH_ASSOC);

            // 2. Desglose de conteos y métricas
            $totalAssigned = count($assignedTickets);
            $resolvedTickets = [];
            $activeTickets = [];
            $prioCounts = ['urgent' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
            $resolutionMinutes = [];

            foreach ($assignedTickets as $t) {
                $statusId = (int)($t['status_id'] ?? 1);
                $spStatus = mb_strtolower(trim($t['sp_status'] ?? ''));
                $isResolved = ($statusId === 4 || $spStatus === 'procesado');
                $prio = strtolower(trim($t['priority'] ?? 'medium'));
                if (!isset($prioCounts[$prio])) {
                    $prio = 'medium';
                }
                $prioCounts[$prio]++;

                if ($isResolved) {
                    $resolvedTickets[] = $t;
                    $created = !empty($t['created_at']) ? strtotime($t['created_at']) : 0;
                    $resolved = !empty($t['resolved_at']) ? strtotime($t['resolved_at']) : (!empty($t['updated_at']) ? strtotime($t['updated_at']) : 0);
                    if ($created > 0 && $resolved > $created) {
                        $resolutionMinutes[] = round(($resolved - $created) / 60);
                    }
                } else {
                    $activeTickets[] = $t;
                }
            }

            $resolvedCount = count($resolvedTickets);
            $activeCount = count($activeTickets);

            // 3. Tickets solicitados (creados por el usuario)
            $stmtReq = $this->db->prepare("SELECT COUNT(*) FROM tickets WHERE requester_id = :uid");
            $stmtReq->execute([':uid' => $userId]);
            $requestedCount = (int)$stmtReq->fetchColumn();

            // 4. Tiempo promedio de resolución
            $avgResolutionMinutes = !empty($resolutionMinutes) ? (array_sum($resolutionMinutes) / count($resolutionMinutes)) : 0;
            $fastestResolutionMinutes = !empty($resolutionMinutes) ? min($resolutionMinutes) : 0;

            $formatMinutes = function($minutes) {
                $minutes = (int)round((float)$minutes);
                if ($minutes <= 0) return 'Sin registros';
                $hours = intdiv($minutes, 60);
                $mins = $minutes % 60;
                if ($hours >= 24) {
                    $days = round($hours / 24, 1);
                    return "{$days} días (" . $hours . " h)";
                }
                if ($hours > 0) {
                    return "{$hours}h {$mins}m";
                }
                return "{$mins} min";
            };

            $avgResolutionText = $formatMinutes($avgResolutionMinutes);
            $fastestResolutionText = $formatMinutes($fastestResolutionMinutes);

            // 5. Cálculo y Ponderación de Dificultad (Complejidad de tickets)
            // Escala de Dificultad: Baja = 1, Media = 2, Alta = 3, Urgente = 4
            $totalPrio = max(1, $totalAssigned);
            $difficultyScore = round(
                (($prioCounts['urgent'] * 4) + ($prioCounts['high'] * 3) + ($prioCounts['medium'] * 2) + ($prioCounts['low'] * 1)) / $totalPrio,
                1
            );

            if ($totalAssigned === 0) {
                $difficultyScore = 2.0;
                $difficultyLabel = 'Estándar / Normal';
                $difficultyColor = '#0284C7';
                $difficultyBadgeClass = 'prio-medium';
            } elseif ($difficultyScore >= 3.3) {
                $difficultyLabel = 'Muy Alta / Crítica';
                $difficultyColor = '#DC2626';
                $difficultyBadgeClass = 'prio-urgent';
            } elseif ($difficultyScore >= 2.5) {
                $difficultyLabel = 'Alta';
                $difficultyColor = '#EA580C';
                $difficultyBadgeClass = 'prio-high';
            } elseif ($difficultyScore >= 1.7) {
                $difficultyLabel = 'Media';
                $difficultyColor = '#D97706';
                $difficultyBadgeClass = 'prio-medium';
            } else {
                $difficultyLabel = 'Baja';
                $difficultyColor = '#059669';
                $difficultyBadgeClass = 'prio-low';
            }

            $prioPercentages = [
                'urgent' => round(($prioCounts['urgent'] / $totalPrio) * 100),
                'high' => round(($prioCounts['high'] / $totalPrio) * 100),
                'medium' => round(($prioCounts['medium'] / $totalPrio) * 100),
                'low' => round(($prioCounts['low'] / $totalPrio) * 100)
            ];

            // 6. Cumplimiento SLA del agente
            $inSlaCount = 0;
            foreach ($assignedTickets as $t) {
                if ((int)($t['is_sla_breached'] ?? 0) === 0) {
                    $inSlaCount++;
                }
            }
            $slaComplianceRate = ($totalAssigned > 0) ? round(($inSlaCount / $totalAssigned) * 100, 1) . '%' : '100%';

            // 7. Actividad reciente del usuario
            $stmtAct = $this->db->prepare("
                SELECT c.comment as comment_text, c.created_at, c.is_internal,
                       t.id as ticket_id, t.code as ticket_code, t.title as ticket_title
                FROM ticket_comments c
                JOIN tickets t ON c.ticket_id = t.id
                WHERE c.user_id = :uid
                ORDER BY c.created_at DESC
                LIMIT 6
            ");
            $stmtAct->execute([':uid' => $userId]);
            $recentActivity = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $userId,
                        'name' => $userName,
                        'alias' => $userAlias,
                        'email' => $userEmail,
                        'role_name' => $currentUser['role_name'] ?? 'colaborador',
                        'area_name' => $currentUser['area_name'] ?? 'General',
                        'created_at' => $currentUser['created_at'] ?? date('Y-m-d')
                    ],
                    'counts' => [
                        'assigned' => $totalAssigned,
                        'resolved' => $resolvedCount,
                        'active' => $activeCount,
                        'requested' => $requestedCount,
                        'resolution_rate' => ($totalAssigned > 0) ? round(($resolvedCount / $totalAssigned) * 100, 1) : 0
                    ],
                    'timing' => [
                        'avg_resolution' => $avgResolutionText,
                        'avg_minutes' => round($avgResolutionMinutes),
                        'fastest_resolution' => $fastestResolutionText,
                        'sla_compliance' => $slaComplianceRate
                    ],
                    'difficulty' => [
                        'score' => $difficultyScore,
                        'max_score' => 4.0,
                        'label' => $difficultyLabel,
                        'color' => $difficultyColor,
                        'badge_class' => $difficultyBadgeClass,
                        'counts' => $prioCounts,
                        'percentages' => $prioPercentages
                    ],
                    'active_tickets' => array_slice($activeTickets, 0, 10),
                    'recent_activity' => $recentActivity
                ]
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Actualizar datos personales y alias/siglas de SharePoint del usuario autenticado
     */
    public function updateOwnProfile(int $userId, array $data, array $currentUser): array {
        try {
            $name = trim($data['name'] ?? '');
            $alias = trim($data['alias'] ?? '') ?: $name;

            if (empty($name)) {
                return ['success' => false, 'message' => 'El nombre completo es obligatorio.'];
            }

            if (empty($alias)) {
                $alias = $name;
            }

            $stmt = $this->db->prepare("UPDATE users SET name = :name, alias = :alias WHERE id = :id");
            $stmt->execute([
                ':name'  => $name,
                ':alias' => $alias,
                ':id'    => $userId
            ]);

            // Actualizar la sesión activa inmediatamente
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['alias'] = $alias;
            }

            return [
                'success' => true, 
                'message' => 'Datos personales y alias actualizados exitosamente.',
                'data' => ['name' => $name, 'alias' => $alias]
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al actualizar perfil: ' . $e->getMessage()];
        }
    }

    /**
     * Actualizar contraseña propia del usuario autenticado
     */
    public function updateOwnPassword(int $userId, string $currentPassword, string $newPassword): array {
        try {
            if (strlen($newPassword) < 6) {
                return ['success' => false, 'message' => 'La nueva contraseña debe tener al menos 6 caracteres.'];
            }
            $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $hash = $stmt->fetchColumn();
            if (!$hash || !password_verify($currentPassword, $hash)) {
                return ['success' => false, 'message' => 'La contraseña actual ingresada es incorrecta.'];
            }
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmtUp = $this->db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            $stmtUp->execute([':hash' => $newHash, ':id' => $userId]);
            return ['success' => true, 'message' => 'Contraseña actualizada correctamente.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}
