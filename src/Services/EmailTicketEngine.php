<?php
namespace Services;

use Config\Database;
use PDO;
use Exception;

class EmailTicketEngine {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Motor Email-to-Ticket: Ingesta mensajes de correo y genera tickets automáticamente
     * 
     * @param string $senderEmail Correo del remitente
     * @param string $senderName Nombre del remitente
     * @param string $subject Asunto del correo
     * @param string $body Cuerpo del mensaje
     * @param string $destinationEmail Buzón de correo de destino (ej: mantenimiento@empresa.com)
     */
    public function ingestEmail(string $senderEmail, string $senderName, string $subject, string $body, string $destinationEmail): array {
        try {
            $senderEmail = strtolower(trim($senderEmail));
            $subject = trim($subject);
            $body = trim($body);

            if (empty($senderEmail) || empty($subject) || empty($body)) {
                return ['success' => false, 'message' => 'Parámetros de correo incompletos.'];
            }

            $this->db->beginTransaction();

            // 1. IDENTIFICACIÓN / MAPEO AUTOMÁTICO DE USUARIOS
            $stmtUser = $this->db->prepare("SELECT id, name FROM users WHERE LOWER(email) = :email");
            $stmtUser->execute([':email' => $senderEmail]);
            $user = $stmtUser->fetch();

            if (!$user) {
                // Crear usuario temporal/nuevo de rol 'requester' (4)
                $tempPasswordHash = password_hash('password123', PASSWORD_DEFAULT);
                $stmtNewUser = $this->db->prepare("
                    INSERT INTO users (name, email, password_hash, role_id, is_active)
                    VALUES (:name, :email, :pass, 4, 1)
                ");
                $stmtNewUser->execute([
                    ':name' => !empty($senderName) ? $senderName : explode('@', $senderEmail)[0],
                    ':email' => $senderEmail,
                    ':pass' => $tempPasswordHash
                ]);
                $userId = (int)$this->db->lastInsertId();
            } else {
                $userId = (int)$user['id'];
            }

            // 2. DETERMINAR ÁREA SEGÚN EL BUZÓN DE CORREO DE DESTINO
            $stmtArea = $this->db->prepare("SELECT id, name FROM areas WHERE LOWER(email_inbound) = :email OR id = 1 LIMIT 1");
            $stmtArea->execute([':email' => strtolower($destinationEmail)]);
            $area = $stmtArea->fetch();
            $areaId = $area ? (int)$area['id'] : 1;

            // 3. GENERAR CÓDIGO ÚNICO DE TICKET
            $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $area['name'] ?? 'EML'), 0, 2));
            $year = date('Y');
            
            $stmtCount = $this->db->prepare("SELECT COUNT(*) as total FROM tickets WHERE area_id = :area_id");
            $stmtCount->execute([':area_id' => $areaId]);
            $count = (int)$stmtCount->fetch()['total'] + 1;
            
            $code = sprintf("%s-%s-%03d", $prefix, $year, $count);

            // 4. CALCULAR SLA (DUE DATE) SEGÚN POLÍTICAS
            $stmtSLA = $this->db->prepare("SELECT max_resolution_hours FROM sla_policies WHERE area_id = :area_id AND priority = 'medium' LIMIT 1");
            $stmtSLA->execute([':area_id' => $areaId]);
            $sla = $stmtSLA->fetch();
            $maxHours = $sla ? (int)$sla['max_resolution_hours'] : 24;
            $dueDate = date('Y-m-d H:i:s', strtotime("+$maxHours hours"));

            // 5. INSERTAR TICKET CON ORIGEN 'email'
            $stmtTicket = $this->db->prepare("
                INSERT INTO tickets (code, title, description, priority, status_id, area_id, requester_id, source, due_date)
                VALUES (:code, :title, :description, 'medium', 1, :area_id, :requester_id, 'email', :due_date)
            ");
            $stmtTicket->execute([
                ':code' => $code,
                ':title' => $subject,
                ':description' => $body,
                ':area_id' => $areaId,
                ':requester_id' => $userId,
                ':due_date' => $dueDate
            ]);
            $ticketId = (int)$this->db->lastInsertId();

            // 6. REGISTRAR EN LOG DE AUDITORÍA INMUTABLE
            $stmtAudit = $this->db->prepare("
                INSERT INTO ticket_audit_logs (ticket_id, user_id, action_type, old_value, new_value)
                VALUES (:ticket_id, :user_id, 'email_ingestion', 'Email Inbound', :new_val)
            ");
            $stmtAudit->execute([
                ':ticket_id' => $ticketId,
                ':user_id' => $userId,
                ':new_val' => "Ticket ingestado automáticamente desde correo: $senderEmail"
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => "Correo procesado y ticket $code generado automáticamente.",
                'ticket_id' => $ticketId,
                'code' => $code
            ];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return ['success' => false, 'message' => 'Error en Ingesta de Correo: ' . $e->getMessage()];
        }
    }
}
