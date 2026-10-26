<?php
namespace Services;

use Config\Database;
use PDO;
use Exception;

class SharePointService {
    private PDO $db;
    private string $tenantId;
    private string $clientId;
    private string $clientSecret;
    private string $siteId;
    private string $listName;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->loadEnvironment();
    }

    /**
     * Cargar variables de entorno desde .env si no están en el proceso
     */
    private function loadEnvironment(): void {
        $envPath = __DIR__ . '/../../.env';
        $envMap = [];
        if (file_exists($envPath)) {
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
                    $envMap[$key] = $val;
                }
            }
        }

        $this->tenantId = getenv('GRAPH_TENANT_ID') ?: ($envMap['GRAPH_TENANT_ID'] ?? '');
        $this->clientId = getenv('GRAPH_CLIENT_ID') ?: ($envMap['GRAPH_CLIENT_ID'] ?? '');
        $this->clientSecret = getenv('GRAPH_CLIENT_SECRET') ?: ($envMap['GRAPH_CLIENT_SECRET'] ?? '');
        $this->siteId = getenv('VITE_SHAREPOINT_SITE_ID') ?: ($envMap['VITE_SHAREPOINT_SITE_ID'] ?? '');
        $this->listName = getenv('VITE_LIST_NAME') ?: ($envMap['VITE_LIST_NAME'] ?? 'Solicitudes - SSGG e IT');
    }

    /**
     * Obtener token OAuth 2.0 de Microsoft Graph API (Client Credentials)
     */
    private function getAccessToken(): string {
        if (empty($this->tenantId) || empty($this->clientId) || empty($this->clientSecret)) {
            throw new Exception("Credenciales de Microsoft Graph incompletas en variables de entorno.");
        }

        $url = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";
        $data = http_build_query([
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope'         => 'https://graph.microsoft.com/.default',
            'grant_type'    => 'client_credentials',
        ]);

        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n" .
                             "Accept: application/json\r\n",
                'content' => $data,
                'timeout' => 15,
            ]
        ];

        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            throw new Exception("Error al obtener token de Graph: " . ($error['message'] ?? 'Error de conexión.'));
        }

        $json = json_decode($response, true);
        if (empty($json['access_token'])) {
            throw new Exception("Respuesta de token inválida de Microsoft Graph.");
        }

        return $json['access_token'];
    }

    /**
     * Obtener ID de la lista en SharePoint mediante GET (Solo Lectura)
     */
    public function getListId(string $token): string {
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/lists";
        $opts = [
            'http' => [
                'method'  => 'GET',
                'header'  => "Authorization: Bearer {$token}\r\nAccept: application/json\r\n",
                'timeout' => 15,
            ]
        ];
        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new Exception("Error al consultar listas de SharePoint.");
        }

        $data = json_decode($response, true);
        if (!empty($data['value'])) {
            foreach ($data['value'] as $list) {
                if (strcasecmp($list['displayName'], $this->listName) === 0) {
                    return $list['id'];
                }
            }
        }

        // Fallback al ID conocido
        return "ad47f040-0c38-4fa7-a8bb-29389cbbce03";
    }

    /**
     * Encontrar el ID más alto existente en la lista de SharePoint (los registros más nuevos)
     */
    public function findMaxSharePointId(string $token, string $listId): int {
        $localMax = 0;
        try {
            $localMax = (int)$this->db->query("SELECT MAX(sharepoint_id) FROM tickets")->fetchColumn();
        } catch (\Throwable $e) {}

        $probeStart = max(11360, $localMax);
        $headerAuth = "Authorization: Bearer {$token}\r\nAccept: application/json\r\n";
        $opts = [
            'http' => [
                'method'  => 'GET',
                'header'  => $headerAuth,
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ];
        $context = stream_context_create($opts);

        $currentMax = $probeStart;
        for ($check = $probeStart; $check <= $probeStart + 300; $check += 10) {
            $u = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/lists/{$listId}/items/{$check}?\$select=id";
            $res = @file_get_contents($u, false, $context);
            if ($res !== false) {
                $d = json_decode($res, true);
                if (!empty($d['id'])) {
                    $currentMax = (int)$d['id'];
                } else {
                    break;
                }
            } else {
                break;
            }
        }

        for ($check = $currentMax + 1; $check <= $currentMax + 15; $check++) {
            $u = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/lists/{$listId}/items/{$check}?\$select=id";
            $res = @file_get_contents($u, false, $context);
            if ($res !== false) {
                $d = json_decode($res, true);
                if (!empty($d['id'])) {
                    $currentMax = (int)$d['id'];
                } else {
                    break;
                }
            } else {
                break;
            }
        }

        return $currentMax;
    }

    /**
     * Consultar registros de SharePoint mediante HTTP GET (Solo Lectura)
     * Procesa desde los registros más recientes (ID mayor descendente) hacia los más antiguos
     * garantizando que las solicitudes recientes de IT y SSGG se sincronicen de inmediato.
     * Soporta límite, filtro por título, rango de tiempo ('1h', '24h', '1w', 'all') y área ('IT', 'SSGG')
     */
    public function fetchSharePointTickets(int $limit = 300, ?string $titleFilter = null, ?string $timeRange = null, ?string $areaFilter = null): array {
        $token = $this->getAccessToken();
        $listId = $this->getListId($token);

        $sinceTimestamp = null;
        if ($timeRange === '1h') {
            $sinceTimestamp = strtotime('-1 hour');
        } elseif ($timeRange === '24h') {
            $sinceTimestamp = strtotime('-48 hours'); // Margen de 48h para garantizar inclusión de registros de ayer y diferencias de zona horaria
        } elseif ($timeRange === '1w') {
            $sinceTimestamp = strtotime('-7 days');
        } elseif ($timeRange === 'recent' || $timeRange === '50') {
            $limit = min($limit, 50);
            $sinceTimestamp = null;
        } elseif ($timeRange === '100') {
            $limit = min($limit, 100);
            $sinceTimestamp = null;
        } elseif ($timeRange === '300') {
            $limit = min($limit, 300);
            $sinceTimestamp = null;
        } elseif ($timeRange === 'all') {
            $sinceTimestamp = null;
        }

        $maxId = $this->findMaxSharePointId($token, $listId);
        $items = [];
        $batchSize = 20;
        $consecutiveOld = 0;
        $maxScan = ($timeRange === 'all') ? max($limit * 3, 1000) : max($limit * 2, 600);

        for ($offset = 0; $offset < $maxScan; $offset += $batchSize) {
            $requests = [];
            for ($i = 0; $i < $batchSize; $i++) {
                $itemId = $maxId - ($offset + $i);
                if ($itemId < 1) break;
                $requests[] = [
                    'id' => (string)$itemId,
                    'method' => 'GET',
                    'url' => "/sites/{$this->siteId}/lists/{$listId}/items/{$itemId}?\$expand=fields"
                ];
            }
            if (empty($requests)) break;

            $ctx = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Authorization: Bearer {$token}\r\nContent-Type: application/json\r\nAccept: application/json\r\n",
                    'content' => json_encode(['requests' => $requests]),
                    'timeout' => 15,
                    'ignore_errors' => true
                ]
            ]);

            $resp = @file_get_contents("https://graph.microsoft.com/v1.0/\$batch", false, $ctx);
            if (!$resp) break;

            $batchData = json_decode($resp, true);
            if (empty($batchData['responses'])) break;

            // Ordenar respuestas de mayor a menor ID
            usort($batchData['responses'], function($a, $b) {
                return (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
            });

            foreach ($batchData['responses'] as $r) {
                if (($r['status'] ?? 0) !== 200 || empty($r['body'])) continue;
                $item = $r['body'];
                $fields = $item['fields'] ?? [];

                // Fecha de registro o última modificación
                $rawDate = $item['lastModifiedDateTime'] ?? $fields['field_6'] ?? $item['createdDateTime'] ?? ($fields['Created'] ?? null);
                $cTime = $rawDate ? strtotime($rawDate) : null;


                if ($sinceTimestamp && $cTime) {
                    if ($cTime < $sinceTimestamp) {
                        $consecutiveOld++;
                        if ($consecutiveOld >= 35) {
                            break 2;
                        }
                        continue;
                    } else {
                        $consecutiveOld = 0;
                    }
                }

                // Filtro de título opcional
                if ($titleFilter) {
                    $itemTitle = trim($fields['Title'] ?? '');
                    if (stripos($itemTitle, $titleFilter) === false) {
                        continue;
                    }
                }

                // Filtro de área opcional (IT vs SSGG)
                if ($areaFilter) {
                    $areaStr = strtoupper(trim($fields['field_1'] ?? ''));
                    $isSSGG = str_contains($areaStr, 'SSGG') || str_contains($areaStr, 'MANTENIMIENTO');
                    if (strtoupper($areaFilter) === 'IT' && $isSSGG) continue;
                    if (strtoupper($areaFilter) === 'SSGG' && !$isSSGG) continue;
                }

                $items[] = $item;
                if (count($items) >= $limit) {
                    break 2;
                }
            }
        }

        return $items;
    }

    /**
     * Asegurar que la tabla tickets tenga la columna sharepoint_id
     * y corregir cualquier mojibake histórico en las tablas de la BD
     */
    public function ensureSchemaAndFixEncoding(): void {
        try {
            // 1. Asegurar columna sharepoint_id en tabla tickets
            $stmt = $this->db->query("SHOW COLUMNS FROM tickets LIKE 'sharepoint_id'");
            $col = $stmt->fetch();
            if (!$col) {
                $this->db->exec("ALTER TABLE tickets ADD COLUMN sharepoint_id INT NULL UNIQUE AFTER id");
            }
            $this->db->exec("ALTER TABLE tickets MODIFY COLUMN code VARCHAR(50) NOT NULL");
            $this->db->exec("ALTER TABLE tickets MODIFY COLUMN title VARCHAR(255) NOT NULL");

            $colsToAdd = [
                'company_name' => "VARCHAR(150) NULL AFTER title",
                'area_code'    => "VARCHAR(20) NULL AFTER area_id",
                'responsable'  => "VARCHAR(100) NULL AFTER assigned_agent_id",
                'observations' => "TEXT NULL AFTER description",
                'sp_status'    => "VARCHAR(50) NULL AFTER status_id"
            ];
            foreach ($colsToAdd as $colName => $colDef) {
                $check = $this->db->query("SHOW COLUMNS FROM tickets LIKE '{$colName}'")->fetch();
                if (!$check) {
                    $this->db->exec("ALTER TABLE tickets ADD COLUMN {$colName} {$colDef}");
                }
            }
        } catch (Exception $e) {
            // Ignorar si ya existe
        }

        try {
            // 2. Corregir mojibake en nombres de áreas
            $this->db->exec("
                UPDATE areas SET name = 'Tecnología e Informática (TI)', description = 'Soporte técnico, redes, infraestructura, servidores y telecomunicaciones.' WHERE id = 1;
                UPDATE areas SET name = 'Recursos Humanos (RRHH)', description = 'Gestión del talento humano, nómina, permisos y certificaciones.' WHERE id = 2;
                UPDATE areas SET name = 'Mantenimiento y Servicios Generales (SSGG)', description = 'Infraestructura física, climatización, fontanería, electricidad y mantenimiento.' WHERE id = 3;
                UPDATE areas SET name = 'Finanzas y Contabilidad', description = 'Sistemas contables, Profit Plus, facturación y reembolsos.' WHERE id = 4;
            ");

            // 3. Corregir mojibake en nombres de usuarios
            $this->db->exec("
                UPDATE users SET name = 'Carlos Mendoza (Coordinador TI)' WHERE id = 2;
                UPDATE users SET name = 'Elena Rostova (Coordinador RRHH)' WHERE id = 3;
                UPDATE users SET name = 'Marcos Benítez (Coordinador Mantenimiento)' WHERE id = 4;
                UPDATE users SET name = 'Juan Pérez (Colaborador TI)' WHERE id = 5;
                UPDATE users SET name = 'Laura Torres (Colaborador TI)' WHERE id = 6;
                UPDATE users SET name = 'Sofía Ramírez (Colaborador RRHH)' WHERE id = 7;
                UPDATE users SET name = 'Ana López (Colaborador TI)' WHERE id = 8;
                UPDATE users SET name = 'Roberto Gómez (Colaborador Mantenimiento)' WHERE id = 9;
            ");

            // 4. Corregir estados kanban
            $this->db->exec("
                UPDATE kanban_status SET name = 'Por Asignar' WHERE id = 1;
                UPDATE kanban_status SET name = 'En Progreso' WHERE id = 2;
                UPDATE kanban_status SET name = 'Pendiente Usuario' WHERE id = 3;
                UPDATE kanban_status SET name = 'Resuelto' WHERE id = 4;
            ");

            // 5. Eliminar tickets de prueba ficticios antiguos sin sharepoint_id
            $this->db->exec("DELETE FROM tickets WHERE sharepoint_id IS NULL");

            // 6. Asegurar existencia de tabla ticket_satisfaction_surveys (CSAT)
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS ticket_satisfaction_surveys (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ticket_id INT NOT NULL,
                    user_id INT NOT NULL,
                    rating INT NOT NULL,
                    feedback TEXT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_survey_ticket (ticket_id),
                    INDEX idx_survey_user (user_id),
                    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } catch (Exception $e) {
            // Ignorar
        }
    }

    /**
     * Limpiar posibles secuencias de doble codificación UTF-8
     */
    private function cleanUtf8(string $str): string {
        if (preg_match('/Ã[\x80-\xBF]/', $str)) {
            $fixed = @utf8_decode($str);
            if ($fixed && mb_check_encoding($fixed, 'UTF-8')) {
                return $fixed;
            }
        }
        return $str;
    }

    /**
     * Sincronizar registros de SharePoint a la tabla local tickets (UPSERT)
     * Soporta límite, filtro por título, rango de tiempo ('1h', '24h', '1w', 'all') y área ('IT', 'SSGG')
     */
    public function syncToDatabase(int $limit = 300, ?string $titleFilter = null, ?string $timeRange = null, ?string $areaFilter = null): array {
        $this->ensureSchemaAndFixEncoding();

        $spItems = $this->fetchSharePointTickets($limit, $titleFilter, $timeRange, $areaFilter);
        $synced = 0;
        $created = 0;
        $updated = 0;

        $stmtCheck = $this->db->prepare("SELECT id FROM tickets WHERE sharepoint_id = :sp_id LIMIT 1");
        $stmtInsert = $this->db->prepare("
            INSERT INTO tickets (
                sharepoint_id, code, title, company_name, area_code, responsable,
                observations, sp_status, description, priority, 
                status_id, area_id, requester_id, assigned_agent_id, 
                due_date, resolved_at, created_at
            ) VALUES (
                :sp_id, :code, :title, :company_name, :area_code, :responsable,
                :observations, :sp_status, :description, :priority,
                :status_id, :area_id, :requester_id, :assigned_agent_id,
                :due_date, :resolved_at, :created_at
            )
        ");

        $stmtUpdate = $this->db->prepare("
            UPDATE tickets SET 
                title = :title,
                company_name = :company_name,
                area_code = :area_code,
                responsable = :responsable,
                observations = :observations,
                sp_status = :sp_status,
                description = :description,
                status_id = :status_id,
                area_id = :area_id,
                requester_id = :requester_id,
                assigned_agent_id = :assigned_agent_id,
                due_date = :due_date,
                resolved_at = :resolved_at
            WHERE sharepoint_id = :sp_id
        ");

        foreach ($spItems as $item) {
            $spId = (int)$item['id'];
            $fields = $item['fields'] ?? [];

            $title = $this->cleanUtf8(trim($fields['Title'] ?? 'Solicitud sin título'));
            if (mb_strlen($title) > 255) {
                $title = mb_substr($title, 0, 252) . '...';
            }

            $empresa = $this->cleanUtf8(trim($fields['field_0'] ?? ''));
            $areaStr = strtoupper(trim($fields['field_1'] ?? ''));
            $responsable = $this->cleanUtf8(trim($fields['field_2'] ?? ''));
            $observaciones = $this->cleanUtf8(trim($fields['field_4'] ?? ''));
            $estatusStr = trim($fields['field_5'] ?? '');

            // 1. Mapeo de Área
            // IT -> 1 (Tecnología e Informática), SSGG -> 3 (Mantenimiento y Servicios Generales)
            if (str_contains($areaStr, 'SSGG') || str_contains($areaStr, 'MANTENIMIENTO')) {
                $areaId = 3;
                $codePrefix = 'SG';
            } else {
                $areaId = 1;
                $codePrefix = 'IT';
            }

            $code = sprintf("%s-SP-%05d", $codePrefix, $spId);

            // 2. Mapeo de Estatus
            // Opciones de SharePoint: Pendiente, En proceso, Stand By, Procesado
            $statusId = match ($estatusStr) {
                'En proceso' => 2,
                'Stand By'   => 3,
                'Procesado'  => 4,
                default      => 1, // 'Pendiente' o sin asignar
            };

            // 3. Descripción y detalles
            $descriptionParts = [];
            if (!empty($observaciones)) {
                $descriptionParts[] = $observaciones;
            }
            if (!empty($empresa)) {
                $descriptionParts[] = "Empresa: {$empresa}";
            }
            if (!empty($responsable)) {
                $descriptionParts[] = "Responsable SharePoint: {$responsable}";
            }
            $description = !empty($descriptionParts) 
                ? implode("\n\n", $descriptionParts) 
                : "Solicitud originada en SharePoint #{$spId}";

            // 4. Fechas
            $createdAt = !empty($fields['field_6']) 
                ? date('Y-m-d H:i:s', strtotime($fields['field_6']))
                : (!empty($item['createdDateTime']) ? date('Y-m-d H:i:s', strtotime($item['createdDateTime'])) : date('Y-m-d H:i:s'));

            $dueDate = !empty($fields['field_7']) 
                ? date('Y-m-d H:i:s', strtotime($fields['field_7'])) 
                : null;

            $resolvedAt = ($statusId === 4) 
                ? (!empty($fields['field_8']) ? date('Y-m-d H:i:s', strtotime($fields['field_8'])) : date('Y-m-d H:i:s')) 
                : null;

            // 5. Agente Asignado (Mapeo directo con lo que viene en 'Responsable' de SharePoint)
            $assignedAgentId = null;
            if (!empty($responsable)) {
                static $agentUserCache = [];
                if (!isset($agentUserCache[$responsable])) {
                    $agentEmailSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $responsable));
                    if (empty($agentEmailSlug)) $agentEmailSlug = 'agente';
                    $agentEmail = "{$agentEmailSlug}@empresa.com";

                    $stmtFindAgent = $this->db->prepare("SELECT id FROM users WHERE name = :name LIMIT 1");
                    $stmtFindAgent->execute([':name' => $responsable]);
                    $existingAgent = $stmtFindAgent->fetch();

                    if ($existingAgent) {
                        $agentUserCache[$responsable] = (int)$existingAgent['id'];
                    } else {
                        // Crear usuario agente con el nombre del Responsable de SharePoint
                        $stmtCreateAgent = $this->db->prepare("
                            INSERT INTO users (area_id, role_id, name, email, password_hash, is_active)
                            VALUES (:area_id, 3, :name, :email, :pass, 1)
                        ");
                        $defaultPass = '$2y$10$qV4eW4vR6p0yR8.XQ24.m0d1FqWuu1.Yy3gE7wO0a123456789012';
                        $stmtCreateAgent->execute([
                            ':area_id' => $areaId,
                            ':name'    => $responsable,
                            ':email'   => $agentEmail,
                            ':pass'    => $defaultPass
                        ]);
                        $agentUserCache[$responsable] = (int)$this->db->lastInsertId();
                    }
                }
                $assignedAgentId = $agentUserCache[$responsable];
            }

            // 6. Solicitante Dinámico: Usar la EMPRESA solicitante (field_0)
            $companyName = !empty($empresa) ? $empresa : 'Empresa General';
            $companyEmailSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $companyName));
            if (empty($companyEmailSlug)) {
                $companyEmailSlug = 'solicitante';
            }
            $companyEmail = "{$companyEmailSlug}@empresa.com";

            // Buscar si ya existe el usuario de la empresa solicitante o crearlo
            static $companyUserCache = [];
            if (!isset($companyUserCache[$companyName])) {
                $stmtFindUser = $this->db->prepare("SELECT id FROM users WHERE name = :name OR email = :email LIMIT 1");
                $stmtFindUser->execute([':name' => $companyName, ':email' => $companyEmail]);
                $existingUser = $stmtFindUser->fetch();

                if ($existingUser) {
                    $companyUserCache[$companyName] = (int)$existingUser['id'];
                } else {
                    $stmtCreateUser = $this->db->prepare("
                        INSERT INTO users (area_id, role_id, name, email, password_hash, is_active)
                        VALUES (:area_id, 3, :name, :email, :pass, 1)
                    ");
                    $defaultPass = '$2y$10$qV4eW4vR6p0yR8.XQ24.m0d1FqWuu1.Yy3gE7wO0a123456789012';
                    $stmtCreateUser->execute([
                        ':area_id' => $areaId,
                        ':name'    => $companyName,
                        ':email'   => $companyEmail,
                        ':pass'    => $defaultPass
                    ]);
                    $companyUserCache[$companyName] = (int)$this->db->lastInsertId();
                }
            }

            $requesterId = $companyUserCache[$companyName] ?? 8;
            $priority = 'medium';

            // Ejecución UPSERT
            $stmtCheck->execute([':sp_id' => $spId]);
            $exists = $stmtCheck->fetch();

            $compNameClean = !empty($empresa) ? $empresa : 'Empresa General';
            $areaCodeClean = !empty($areaStr) ? $areaStr : ($areaId === 3 ? 'SSGG' : 'IT');
            $responsableClean = !empty($responsable) ? $responsable : 'Sin Asignar';
            $spStatusClean = !empty($estatusStr) ? $estatusStr : 'Pendiente';

            if ($exists) {
                $stmtUpdate->execute([
                    ':title'             => $title,
                    ':company_name'      => $compNameClean,
                    ':area_code'         => $areaCodeClean,
                    ':responsable'       => $responsableClean,
                    ':observations'      => $observaciones,
                    ':sp_status'         => $spStatusClean,
                    ':description'       => $description,
                    ':status_id'         => $statusId,
                    ':area_id'           => $areaId,
                    ':requester_id'      => $requesterId,
                    ':assigned_agent_id' => $assignedAgentId,
                    ':due_date'          => $dueDate,
                    ':resolved_at'       => $resolvedAt,
                    ':sp_id'             => $spId,
                ]);
                $updated++;
            } else {
                $stmtInsert->execute([
                    ':sp_id'             => $spId,
                    ':code'              => $code,
                    ':title'             => $title,
                    ':company_name'      => $compNameClean,
                    ':area_code'         => $areaCodeClean,
                    ':responsable'       => $responsableClean,
                    ':observations'      => $observaciones,
                    ':sp_status'         => $spStatusClean,
                    ':description'       => $description,
                    ':priority'          => $priority,
                    ':status_id'         => $statusId,
                    ':area_id'           => $areaId,
                    ':requester_id'      => $requesterId,
                    ':assigned_agent_id' => $assignedAgentId,
                    ':due_date'          => $dueDate,
                    ':resolved_at'       => $resolvedAt,
                    ':created_at'        => $createdAt,
                ]);
                $created++;
            }
            $synced++;
        }

        $message = "Sincronización completada: {$synced} tickets procesados ({$created} nuevos, {$updated} actualizados).";
        if ($synced === 0 && in_array($timeRange, ['1h', '24h', '1w'], true)) {
            $message = "No se encontraron tickets con actividad en el rango seleccionado ({$timeRange}). Sugerencia: utiliza 'Últimos 50 tickets' para sincronizar los registros más recientes.";
        }

        return [
            'success' => true,
            'total_fetched' => count($spItems),
            'synced'        => $synced,
            'created'       => $created,
            'updated'       => $updated,
            'message'       => $message
        ];
    }


    /**
     * Consultar campos directos de un registro específico en SharePoint mediante ID
     */
    public function getSharePointItem(int $itemId): ?array {
        try {
            $token = $this->getAccessToken();
            $listId = $this->getListId($token);
            $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/lists/{$listId}/items/{$itemId}?\$expand=fields";
            $opts = [
                'http' => [
                    'method'  => 'GET',
                    'header'  => "Authorization: Bearer {$token}\r\nAccept: application/json\r\n",
                    'timeout' => 15,
                    'ignore_errors' => true
                ]
            ];
            $context = stream_context_create($opts);
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return null;
            }
            $data = json_decode($response, true);
            return $data['fields'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Crear un nuevo registro en SharePoint mediante Microsoft Graph API POST
     *
     * IMPORTANTE: Respeta el modo seguro 'dry_run' para evitar modificar
     * la lista de producción de SharePoint durante pruebas locales automatizadas.
     */
    public function createSharePointItem(array $data): array {
        // Validación de modo seguro / dry-run para pruebas
        $isDryRun = (getenv('SHAREPOINT_DRY_RUN') === 'true') || (!empty($data['dry_run']));
        if ($isDryRun) {
            $simulatedId = 990000 + mt_rand(1, 9999);
            return [
                'success'   => true,
                'dry_run'   => true,
                'item_id'   => $simulatedId,
                'message'   => "[Modo Seguro / Dry-Run] Ítem validado correctamente (ID simulado #{$simulatedId}) sin modificar producción de SharePoint."
            ];
        }

        try {
            $token = $this->getAccessToken();
            $listId = $this->getListId($token);
            $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/lists/{$listId}/items";

            $title = trim((string)($data['title'] ?? 'Nueva Solicitud'));
            if ($title === '') {
                $title = 'Nueva Solicitud';
            }

            $areaCode = strtoupper(trim((string)($data['area_code'] ?? 'IT')));
            if (!in_array($areaCode, ['IT', 'SSGG'], true)) {
                $areaCode = 'IT';
            }

            $fieldsPayload = [
                'Title'   => $title,
                'field_0' => trim((string)($data['company_name'] ?? 'Kaizen Holdings')),
                'field_1' => $areaCode,
                'field_4' => trim((string)($data['observations'] ?? ($data['description'] ?? ''))),
                'field_5' => trim((string)($data['sp_status'] ?? 'Pendiente'))
            ];

            if (!empty($data['responsable'])) {
                $fieldsPayload['field_2'] = trim((string)$data['responsable']);
            }

            $jsonPayload = json_encode(['fields' => $fieldsPayload], JSON_UNESCAPED_UNICODE);

            $opts = [
                'http' => [
                    'method'        => 'POST',
                    'header'        => "Authorization: Bearer {$token}\r\n" .
                                       "Content-Type: application/json\r\n" .
                                       "Accept: application/json\r\n",
                    'content'       => $jsonPayload,
                    'timeout'       => 20,
                    'ignore_errors' => true
                ]
            ];

            $context = stream_context_create($opts);
            $response = @file_get_contents($url, false, $context);

            $statusLine = $http_response_header[0] ?? '';
            preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $match);
            $httpCode = isset($match[1]) ? (int)$match[1] : 0;

            if ($httpCode >= 200 && $httpCode < 300) {
                $resData = json_decode($response, true) ?: [];
                $newId = (int)($resData['id'] ?? ($resData['fields']['id'] ?? 0));
                return [
                    'success'   => true,
                    'http_code' => $httpCode,
                    'item_id'   => $newId,
                    'data'      => $resData,
                    'message'   => "Registro creado exitosamente en SharePoint (ID: #{$newId})."
                ];
            }

            $errorMsg = 'Error al comunicarse con Microsoft Graph.';
            if (!empty($response)) {
                $errJson = json_decode($response, true);
                if (!empty($errJson['error']['message'])) {
                    $errorMsg = $errJson['error']['message'];
                } else {
                    $errorMsg = mb_substr($response, 0, 200);
                }
            }

            return [
                'success'   => false,
                'http_code' => $httpCode,
                'message'   => "Error en SharePoint (HTTP {$httpCode}): {$errorMsg}"
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Excepción al crear en SharePoint: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar campos de un registro específico en SharePoint mediante Microsoft Graph API PATCH
     */
    public function updateSharePointItem(int $itemId, array $updatedFields): array {
        try {
            $token = $this->getAccessToken();
            $listId = $this->getListId($token);
            $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/lists/{$listId}/items/{$itemId}/fields";

            // Mapeo seguro a columnas internas de SharePoint
            $fieldsPayload = [];
            if (isset($updatedFields['title'])) {
                $fieldsPayload['Title'] = trim((string)$updatedFields['title']);
            }
            if (isset($updatedFields['company_name'])) {
                $fieldsPayload['field_0'] = trim((string)$updatedFields['company_name']);
            }
            if (isset($updatedFields['area_code'])) {
                $fieldsPayload['field_1'] = strtoupper(trim((string)$updatedFields['area_code']));
            }
            if (isset($updatedFields['responsable'])) {
                $fieldsPayload['field_2'] = trim((string)$updatedFields['responsable']);
            }
            if (array_key_exists('observations', $updatedFields)) {
                $fieldsPayload['field_4'] = (string)$updatedFields['observations'];
            }
            if (isset($updatedFields['sp_status'])) {
                $fieldsPayload['field_5'] = trim((string)$updatedFields['sp_status']);
            }

            if (empty($fieldsPayload)) {
                return [
                    'success' => false,
                    'message' => 'No se enviaron campos válidos para actualizar.'
                ];
            }

            $jsonPayload = json_encode($fieldsPayload, JSON_UNESCAPED_UNICODE);

            $opts = [
                'http' => [
                    'method'        => 'PATCH',
                    'header'        => "Authorization: Bearer {$token}\r\n" .
                                       "Content-Type: application/json\r\n" .
                                       "Accept: application/json\r\n",
                    'content'       => $jsonPayload,
                    'timeout'       => 15,
                    'ignore_errors' => true
                ]
            ];

            $context = stream_context_create($opts);
            $response = @file_get_contents($url, false, $context);

            $statusLine = $http_response_header[0] ?? '';
            preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $match);
            $httpCode = isset($match[1]) ? (int)$match[1] : 0;

            if ($httpCode >= 200 && $httpCode < 300) {
                $resData = json_decode($response, true) ?: [];
                return [
                    'success'   => true,
                    'http_code' => $httpCode,
                    'fields'    => $resData,
                    'message'   => 'Registro actualizado exitosamente en SharePoint.'
                ];
            }

            $errorMsg = 'Error al comunicarse con Microsoft Graph.';
            if (!empty($response)) {
                $errJson = json_decode($response, true);
                if (!empty($errJson['error']['message'])) {
                    $errorMsg = $errJson['error']['message'];
                } else {
                    $errorMsg = mb_substr($response, 0, 200);
                }
            }

            return [
                'success'   => false,
                'http_code' => $httpCode,
                'message'   => "Error en SharePoint (HTTP {$httpCode}): {$errorMsg}"
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Excepción al actualizar en SharePoint: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar un registro específico en SharePoint mediante Microsoft Graph API DELETE
     *
     * IMPORTANTE: Respeta el modo seguro 'dry_run' para evitar borrar
     * registros de la lista de producción de SharePoint durante pruebas locales.
     */
    public function deleteSharePointItem(int $itemId, bool $dryRun = false): array {
        $isDryRun = (getenv('SHAREPOINT_DRY_RUN') === 'true') || $dryRun;
        if ($isDryRun) {
            return [
                'success' => true,
                'dry_run' => true,
                'message' => "[Modo Seguro / Dry-Run] Ítem #{$itemId} validado para eliminación sin modificar producción de SharePoint."
            ];
        }

        try {
            $token = $this->getAccessToken();
            $listId = $this->getListId($token);
            $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/lists/{$listId}/items/{$itemId}";

            $opts = [
                'http' => [
                    'method'        => 'DELETE',
                    'header'        => "Authorization: Bearer {$token}\r\nAccept: application/json\r\n",
                    'timeout'       => 15,
                    'ignore_errors' => true
                ]
            ];

            $context = stream_context_create($opts);
            $response = @file_get_contents($url, false, $context);

            $statusLine = $http_response_header[0] ?? '';
            preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $match);
            $httpCode = isset($match[1]) ? (int)$match[1] : 0;

            if ($httpCode === 204 || ($httpCode >= 200 && $httpCode < 300)) {
                return [
                    'success'   => true,
                    'http_code' => $httpCode,
                    'message'   => "Registro #{$itemId} eliminado exitosamente en SharePoint."
                ];
            }

            if ($httpCode === 404) {
                return [
                    'success'   => true,
                    'http_code' => 404,
                    'message'   => "El registro #{$itemId} no existía en SharePoint o ya había sido eliminado previamente."
                ];
            }

            $errorMsg = 'Error al comunicarse con Microsoft Graph.';
            if (!empty($response)) {
                $errJson = json_decode($response, true);
                if (!empty($errJson['error']['message'])) {
                    $errorMsg = $errJson['error']['message'];
                } else {
                    $errorMsg = mb_substr($response, 0, 200);
                }
            }

            return [
                'success'   => false,
                'http_code' => $httpCode,
                'message'   => "Error en SharePoint (HTTP {$httpCode}): {$errorMsg}"
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Excepción al eliminar en SharePoint: ' . $e->getMessage()
            ];
        }
    }
}

