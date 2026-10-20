<?php
namespace Controllers;

use Config\Database;
use PDO;
use Exception;

class AreaController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Obtener listado de todas las áreas/departamentos
     */
    public function getAllAreas(): array {
        try {
            $stmt = $this->db->query("
                SELECT a.id, a.name, a.description, a.is_active,
                       u.name as manager_name, u.email as manager_email,
                       (SELECT COUNT(*) FROM tickets t WHERE t.area_id = a.id) as total_tickets
                FROM areas a
                LEFT JOIN users u ON a.manager_id = u.id
                ORDER BY a.name ASC
            ");
            return ['success' => true, 'data' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Crear nuevo Departamento/Área
     */
    public function createArea(array $data): array {
        try {
            $name = trim($data['name'] ?? '');
            $description = trim($data['description'] ?? '');
            $managerId = !empty($data['manager_id']) ? (int)$data['manager_id'] : null;

            if (empty($name)) {
                return ['success' => false, 'message' => 'El nombre del área es obligatorio.'];
            }

            $stmt = $this->db->prepare("
                INSERT INTO areas (name, description, manager_id)
                VALUES (:name, :description, :manager_id)
            ");
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':manager_id' => $managerId
            ]);

            return ['success' => true, 'message' => 'Área/Departamento creado correctamente.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Asignar Responsable/Jefe de Área
     */
    public function assignAreaManager(int $areaId, int $managerId): array {
        try {
            $stmt = $this->db->prepare("UPDATE areas SET manager_id = :manager_id WHERE id = :area_id");
            $stmt->execute([':manager_id' => $managerId, ':area_id' => $areaId]);

            // Actualizar el área del usuario para mantener coherencia
            $stmtUser = $this->db->prepare("UPDATE users SET area_id = :area_id WHERE id = :manager_id");
            $stmtUser->execute([':area_id' => $areaId, ':manager_id' => $managerId]);

            return ['success' => true, 'message' => 'Responsable de área asignado exitosamente.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
