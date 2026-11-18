<?php
namespace Services;

use Config\Database;
use PDO;
use Exception;

class SharePointService {
    private ?PDO $db = null;
    private string $tenantId;
    private string $clientId;
    private string $clientSecret;
    private string $siteId;
    private string $listName;

    /**
     * Nombre interno de la columna de SharePoint que guarda el SOLICITANTE
     * (por ejemplo "field_3"). Se configura con SHAREPOINT_FIELD_REQUESTED_BY
     * en el .env. Si queda vacio, el solicitante vive SOLO en la base local y
     * no se lee ni se escribe nada en SharePoint.
     */
    private string $fieldRequestedBy = '';

    /** Cache del tipo de la columna del solicitante ('text', 'personOrGroup', ...) */
    private ?string $fieldRequestedByType = null;

    /** Cache de resoluciones nombre/correo -> LookupId del usuario del sitio */
    private array $siteUserCache = [];

    /** Ultimo aviso no bloqueante generado al hablar con SharePoint */
    private string $lastWarning = '';

    public function __construct(?PDO $db = null) {
        if ($db !== null) {
            $this->db = $db;
        } else {
            try {
                $this->db = Database::getConnection();
            } catch (\Throwable $e) {
                $this->db = null;
            }
        }
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

        $this->tenantId = getenv('GRAPH_TENANT_ID') ?: ($_ENV['GRAPH_TENANT_ID'] ?? ($_SERVER['GRAPH_TENANT_ID'] ?? ($envMap['GRAPH_TENANT_ID'] ?? '')));
        $this->clientId = getenv('GRAPH_CLIENT_ID') ?: ($_ENV['GRAPH_CLIENT_ID'] ?? ($_SERVER['GRAPH_CLIENT_ID'] ?? ($envMap['GRAPH_CLIENT_ID'] ?? '')));
        $this->clientSecret = getenv('GRAPH_CLIENT_SECRET') ?: ($_ENV['GRAPH_CLIENT_SECRET'] ?? ($_SERVER['GRAPH_CLIENT_SECRET'] ?? ($envMap['GRAPH_CLIENT_SECRET'] ?? '')));
        $this->siteId = getenv('VITE_SHAREPOINT_SITE_ID') ?: ($_ENV['VITE_SHAREPOINT_SITE_ID'] ?? ($_SERVER['VITE_SHAREPOINT_SITE_ID'] ?? ($envMap['VITE_SHAREPOINT_SITE_ID'] ?? '')));
        $this->listName = getenv('VITE_LIST_NAME') ?: ($_ENV['VITE_LIST_NAME'] ?? ($_SERVER['VITE_LIST_NAME'] ?? ($envMap['VITE_LIST_NAME'] ?? 'Solicitudes - SSGG e IT')));

        $rawFieldReqBy = getenv('SHAREPOINT_FIELD_REQUESTED_BY') ?: ($_ENV['SHAREPOINT_FIELD_REQUESTED_BY'] ?? ($_SERVER['SHAREPOINT_FIELD_REQUESTED_BY'] ?? ($envMap['SHAREPOINT_FIELD_REQUESTED_BY'] ?? '')));
        $rawFieldReqBy = trim((string)$rawFieldReqBy);
        // Solo se aceptan nombres internos validos de columna (letras, numeros y _)
        $this->fieldRequestedBy = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $rawFieldReqBy) ? $rawFieldReqBy : '';
    }

    /**
     * Nombre interno de la columna de SharePoint mapeada al solicitante.
     * Cadena vacia = integracion desactivada (el dato queda solo en local).
     */
    public function getRequestedByField(): string {
        return $this->fieldRequestedBy;
    }

    /**
     * Ultimo aviso no bloqueante (por ejemplo, un solicitante que no pudo
     * resolverse como usuario de SharePoint). Se consume y se limpia.
     */
    public function consumeLastWarning(): string {
        $w = $this->lastWarning;
        $this->lastWarning = '';
        return $w;
    }

    /**
     * Tipo de una columna de la lista (OPERACION DE SOLO LECTURA, cacheada).
     * Devuelve 'text', 'personOrGroup', 'choice', 'dateTime', ... o 'desconocido'.
     */
    public function getColumnType(string $internalName): string {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $internalName)) {
            return 'desconocido';
        }

        try {
            $token = $this->getAccessToken();
            $listId = $this->getListId($token);
            $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/lists/{$listId}/columns/{$internalName}";

            $context = stream_context_create([
                'http' => [
                    'method'  => 'GET',
                    'header'  => "Authorization: Bearer {$token}\r\nAccept: application/json\r\n",
                    'timeout' => 10,
                    'ignore_errors' => true
                ]
            ]);
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return 'desconocido';
            }

            $col = json_decode($response, true);
            foreach (['text', 'personOrGroup', 'choice', 'dateTime', 'number', 'boolean', 'lookup', 'currency', 'hyperlinkOrPicture', 'calculated'] as $t) {
                if (isset($col[$t])) {
                    return $t;
                }
            }
            return 'desconocido';
        } catch (\Throwable $e) {
            return 'desconocido';
        }
    }

    /**
     * ¿La columna del solicitante es de tipo "Persona o grupo"?
     * El resultado se cachea por instancia para no repetir la consulta.
     */
    private function requestedByIsPersonField(): bool {
        if ($this->fieldRequestedBy === '') {
            return false;
        }
        if ($this->fieldRequestedByType === null) {
            $this->fieldRequestedByType = $this->getColumnType($this->fieldRequestedBy);
        }
        return ($this->fieldRequestedByType === 'personOrGroup');
    }

    /**
     * Resolver un nombre o correo al LookupId del usuario dentro del sitio
     * (OPERACION DE SOLO LECTURA sobre la "User Information List" del sitio).
     *
     * Las columnas de tipo Persona no aceptan texto: Graph exige escribir en
     * "<columna>LookupId" el identificador numerico del usuario del sitio.
     *
     * Devuelve null si no se encuentra una coincidencia inequivoca.
     */
    public function resolveSiteUserLookupId(string $nameOrEmail): ?int {
        $needle = trim($nameOrEmail);
        if ($needle === '') {
            return null;
        }

        $cacheKey = mb_strtolower($needle);
        if (array_key_exists($cacheKey, $this->siteUserCache)) {
            return $this->siteUserCache[$cacheKey];
        }

        $found = null;
        try {
            $token = $this->getAccessToken();
            $base = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/lists/User Information List/items"
                  . "?expand=fields(select=Title,EMail,UserName,Name)&\$top=200";
            $url = str_replace(' ', '%20', $base);

            $context = stream_context_create([
                'http' => [
                    'method'  => 'GET',
                    'header'  => "Authorization: Bearer {$token}\r\nAccept: application/json\r\n"
                               . "Prefer: HonorNonIndexedQueriesWarningMayFailRandomly\r\n",
                    'timeout' => 15,
                    'ignore_errors' => true
                ]
            ]);

            $guard = 0;
            while ($url !== null && $guard < 25) {
                $guard++;
                $response = @file_get_contents($url, false, $context);
                if ($response === false) {
                    break;
                }
                $data = json_decode($response, true);
                if (empty($data['value']) || !is_array($data['value'])) {
                    break;
                }

                foreach ($data['value'] as $item) {
                    $f = $item['fields'] ?? [];
                    $title = mb_strtolower(trim((string)($f['Title'] ?? '')));
                    $email = mb_strtolower(trim((string)($f['EMail'] ?? '')));
                    if ($title === $cacheKey || ($email !== '' && $email === $cacheKey)) {
                        $found = (int)($item['id'] ?? 0);
                        break 2;
                    }
                }

                $url = $data['@odata.nextLink'] ?? null;
            }
        } catch (\Throwable $e) {
            $found = null;
        }

        if ($found !== null && $found <= 0) {
            $found = null;
        }
        $this->siteUserCache[$cacheKey] = $found;
        return $found;
    }

    /**
     * ¿La porcion horaria "HH:MM:SS" corresponde a una hora real de negocio?
     *
     * Las columnas de SOLO FECHA de SharePoint guardan la medianoche de la zona
     * horaria del sitio, que al serializarse a UTC cae siempre EN PUNTO
     * (por ejemplo 08:00:00Z). Una hora escrita por una persona practicamente
     * nunca cae exacta en la hora, asi que exigir minutos o segundos distintos
     * de cero separa con fiabilidad un dato real de un artefacto de formato.
     */
    private function esHoraReal(string $hms): bool {
        if (!preg_match('/^(\d{2}):(\d{2}):(\d{2})$/', $hms, $m)) {
            return false;
        }
        return ($m[2] !== '00' || $m[3] !== '00');
    }

    /**
     * Extraer quien cargo el elemento en SharePoint a partir de la faceta
     * createdBy que Graph ya devuelve resuelta (nombre y correo), a diferencia
     * de las columnas de persona, que solo entregan un LookupId.
     *
     * Devuelve ['name' => ?string, 'email' => ?string].
     *
     * Los elementos creados por esta propia aplicacion figuran como
     * "Aplicacion de SharePoint" y sin correo; en ese caso no hay una persona
     * real que mostrar y se devuelve null para que manden los datos locales.
     */
    private function extractItemAuthor(array $item): array {
        $user = $item['createdBy']['user'] ?? [];
        $name  = trim((string)($user['displayName'] ?? ''));
        $email = trim((string)($user['email'] ?? ''));

        // Varias cuentas de servicio llegan con doble espacio en el nombre
        $name = trim(preg_replace('/\s+/u', ' ', $name));

        if ($name === '' && $email === '') {
            return ['name' => null, 'email' => null];
        }

        // Sin correo y con nombre de cuenta tecnica => no es una persona.
        // Con correo se acepta siempre: es un usuario real del directorio.
        if ($email === '') {
            $tecnicas = '/(aplicaci[oó]n de sharepoint|sharepoint app|system account|microsoft graph|app sharepoint)/iu';
            if ($name === '' || preg_match($tecnicas, $name)) {
                return ['name' => null, 'email' => null];
            }
        }

        return [
            'name'  => $name !== '' ? $this->cleanUtf8($name) : null,
            'email' => $email !== '' ? $email : null,
        ];
    }

    /**
     * Normalizar a texto el valor de la columna del solicitante tal como lo
     * devuelve Graph. Las columnas de persona llegan como objeto; las de texto
     * como cadena.
     */
    private function extractRequestedByValue($raw): string {
        if (is_array($raw)) {
            foreach (['LookupValue', 'DisplayName', 'Title', 'Email', 'EMail'] as $k) {
                if (!empty($raw[$k]) && is_string($raw[$k])) {
                    return trim($raw[$k]);
                }
            }
            return '';
        }
        return trim((string)$raw);
    }

    /**
     * Construir la entrada del payload de Graph para el solicitante.
     * Devuelve [] si no hay columna mapeada o si no se puede resolver la persona.
     */
    private function buildRequestedByPayload(string $value): array {
        if ($this->fieldRequestedBy === '') {
            return [];
        }

        $value = trim($value);

        if (!$this->requestedByIsPersonField()) {
            // Columna de texto: se escribe el nombre tal cual.
            return [$this->fieldRequestedBy => $value];
        }

        // Columna de Persona o grupo: requiere el LookupId del usuario del sitio.
        // Un valor vacio NO borra la persona asignada en SharePoint: preferimos
        // no tocar el dato de produccion antes que arriesgarnos a perderlo.
        if ($value === '') {
            return [];
        }

        $lookupId = $this->resolveSiteUserLookupId($value);
        if ($lookupId === null) {
            $this->lastWarning = "No se encontró a \"{$value}\" como usuario de SharePoint, "
                . "así que el solicitante quedó guardado sólo en el sistema. "
                . "Escríbelo igual que en el directorio (nombre completo) o usa su correo.";
            return [];
        }

        return [$this->fieldRequestedBy . 'LookupId' => (string)$lookupId];
    }

    /**
     * Listar las columnas de la lista de SharePoint (OPERACION DE SOLO LECTURA).
     *
     * Sirve para identificar el nombre interno real de la columna "Solicitante"
     * antes de habilitar la sincronizacion, y asi no escribir en una columna
     * equivocada de la lista de produccion.
     */
    public function getListColumns(): array {
        try {
            $token = $this->getAccessToken();
            $listId = $this->getListId($token);
            $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/lists/{$listId}/columns";

            $context = stream_context_create([
                'http' => [
                    'method'  => 'GET',
                    'header'  => "Authorization: Bearer {$token}\r\nAccept: application/json\r\n",
                    'timeout' => 15,
                    'ignore_errors' => true
                ]
            ]);

            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return ['success' => false, 'message' => 'No se pudo consultar las columnas de la lista.'];
            }

            $data = json_decode($response, true);
            if (empty($data['value']) || !is_array($data['value'])) {
                $apiMsg = $data['error']['message'] ?? 'Respuesta inesperada de Microsoft Graph.';
                return ['success' => false, 'message' => $apiMsg];
            }

            $columns = [];
            foreach ($data['value'] as $col) {
                $type = 'desconocido';
                foreach (['text', 'choice', 'dateTime', 'number', 'boolean', 'lookup', 'personOrGroup', 'currency', 'hyperlinkOrPicture', 'calculated'] as $t) {
                    if (isset($col[$t])) {
                        $type = $t;
                        break;
                    }
                }
                $columns[] = [
                    'name'         => $col['name'] ?? '',
                    'display_name' => $col['displayName'] ?? '',
                    'type'         => $type,
                    'read_only'    => !empty($col['readOnly']),
                    'hidden'       => !empty($col['hidden']),
                ];
            }

            return [
                'success'           => true,
                'list_name'         => $this->listName,
                'mapped_field'      => $this->fieldRequestedBy,
                'mapped_field_type' => ($this->fieldRequestedBy !== '')
                    ? $this->getColumnType($this->fieldRequestedBy)
                    : '',
                'total'             => count($columns),
                'columns'           => $columns,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
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
     * Implementa una búsqueda exponencial y binaria tolerante a saltos y registros eliminados.
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
                'method'        => 'GET',
                'header'        => $headerAuth,
                'timeout'       => 6,
                'ignore_errors' => true
            ]
        ];
        $context = stream_context_create($opts);

        $exists = function(int $id) use ($token, $listId, $context): bool {
            if ($id < 1) return false;
            $u = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/lists/{$listId}/items/{$id}?\$select=id";
            $res = @file_get_contents($u, false, $context);
            if ($res === false) return false;
            $d = json_decode($res, true);
            return !empty($d['id']);
        };

        // Paso 1: Encontrar un límite superior seguro (Upper Bound)
        $low = $probeStart;
        $step = 50;
        $high = $low;
        $ceiling = $low + 100;

        while (true) {
            $next = $high + $step;
            if ($exists($next)) {
                $high = $next;
                $low = $next;
                $step = min($step * 2, 200);
            } else {
                // Verificar si es un salto vacío aislado o realmente llegamos al final
                if ($exists($next + 20) || $exists($next + 50)) {
                    $high = $next + 50;
                    $low = $high;
                } else {
                    $ceiling = $next + 50;
                    break;
                }
            }
        }

        // Paso 2: Búsqueda binaria entre $low y $ceiling
        $left = $low;
        $right = $ceiling;
        $maxFound = $low;

        while ($left <= $right) {
            $mid = intdiv($left + $right, 2);
            $midExists = $exists($mid) || $exists($mid + 1) || $exists($mid + 2);
            if ($midExists) {
                if ($exists($mid + 2)) $midMax = $mid + 2;
                elseif ($exists($mid + 1)) $midMax = $mid + 1;
                else $midMax = $mid;

                $maxFound = max($maxFound, $midMax);
                $left = $midMax + 1;
            } else {
                $right = $mid - 1;
            }
        }

        // Paso 3: Afinar hacia adelante desde $maxFound paso a paso
        $consecutiveMisses = 0;
        for ($c = $maxFound + 1; $c <= $maxFound + 50; $c++) {
            if ($exists($c)) {
                $maxFound = $c;
                $consecutiveMisses = 0;
            } else {
                $consecutiveMisses++;
                if ($consecutiveMisses >= 10) {
                    break;
                }
            }
        }

        return $maxFound;
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
                'requested_by' => "VARCHAR(150) NULL AFTER company_name",
                'sp_created_by' => "VARCHAR(150) NULL AFTER requested_by",
                'sp_created_by_email' => "VARCHAR(150) NULL AFTER sp_created_by",
                'creation_token' => "VARCHAR(64) NULL AFTER code",
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

            // Indice unico del token de creacion: es lo que impide, a nivel de
            // base de datos, que un mismo envio del formulario genere dos tickets.
            $idx = $this->db->query("SHOW INDEX FROM tickets WHERE Key_name = 'uniq_creation_token'")->fetch();
            if (!$idx) {
                $this->db->exec("ALTER TABLE tickets ADD UNIQUE KEY uniq_creation_token (creation_token)");
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

            // 5. Los tickets locales sin sharepoint_id se conservan y pueden ser exportados
            // (no se eliminan registros para proteger datos del usuario)

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

        // Si la columna del solicitante esta mapeada, su valor se trae de SharePoint;
        // si no, NO se toca la columna local (el dato lo gestiona solo el sistema).
        $spFieldReqBy = $this->fieldRequestedBy;
        $syncRequestedBy = ($spFieldReqBy !== '');
        $requestedByNotice = '';

        // Una columna de tipo Persona guarda un LookupId, no un nombre. Si no se
        // puede resolver ese identificador contra los usuarios del sitio, traerlo
        // solo serviria para ensuciar o vaciar el dato local: mejor no sincronizarlo.
        if ($syncRequestedBy && $this->requestedByIsPersonField()) {
            $syncRequestedBy = false;
            $requestedByNotice = " La columna \"{$spFieldReqBy}\" es de tipo Persona:"
                . " el solicitante se mantiene solo en el sistema y la sincronizacion no lo modifica.";
        }

        $stmtCheck = $this->db->prepare("SELECT id, created_at FROM tickets WHERE sharepoint_id = :sp_id LIMIT 1");
        $insertReqByCol = $syncRequestedBy ? 'requested_by, ' : '';
        $insertReqByVal = $syncRequestedBy ? ':requested_by, ' : '';
        // COALESCE protege el dato local: si :requested_by llega null (SharePoint
        // no aporta un nombre utilizable), la columna conserva su valor actual.
        $updateReqBySet = $syncRequestedBy ? 'requested_by = COALESCE(:requested_by, requested_by),' : '';

        $stmtInsert = $this->db->prepare("
            INSERT INTO tickets (
                sharepoint_id, code, title, company_name, {$insertReqByCol}sp_created_by, sp_created_by_email, area_code, responsable,
                observations, sp_status, description, priority, 
                status_id, area_id, requester_id, assigned_agent_id, 
                due_date, resolved_at, created_at
            ) VALUES (
                :sp_id, :code, :title, :company_name, {$insertReqByVal}:sp_created_by, :sp_created_by_email, :area_code, :responsable,
                :observations, :sp_status, :description, :priority,
                :status_id, :area_id, :requester_id, :assigned_agent_id,
                :due_date, :resolved_at, :created_at
            )
        ");

        $stmtUpdate = $this->db->prepare("
            UPDATE tickets SET 
                title = :title,
                company_name = :company_name,
                {$updateReqBySet}
                sp_created_by = :sp_created_by,
                sp_created_by_email = :sp_created_by_email,
                area_code = :area_code,
                responsable = COALESCE(:responsable, responsable),
                observations = :observations,
                sp_status = :sp_status,
                description = :description,
                status_id = :status_id,
                area_id = :area_id,
                requester_id = :requester_id,
                assigned_agent_id = COALESCE(:assigned_agent_id, assigned_agent_id),
                due_date = :due_date,
                resolved_at = :resolved_at,
                created_at = :created_at
            WHERE sharepoint_id = :sp_id
        ");

        foreach ($spItems as $item) {
            $spId = (int)$item['id'];
            $fields = $item['fields'] ?? [];

            // Registro local previo (si existe): necesario para NO perder la hora de
            // solicitud ya registrada en el sistema cuando SharePoint no la aporta.
            $stmtCheck->execute([':sp_id' => $spId]);
            $exists = $stmtCheck->fetch();
            $localCreatedAt = $exists['created_at'] ?? null;

            $title = $this->cleanUtf8(trim($fields['Title'] ?? 'Solicitud sin título'));
            if (mb_strlen($title) > 255) {
                $title = mb_substr($title, 0, 252) . '...';
            }

            $empresa = $this->cleanUtf8(trim($fields['field_0'] ?? ''));
            $areaStr = strtoupper(trim($fields['field_1'] ?? ''));
            $responsable = $this->cleanUtf8(trim($fields['field_2'] ?? ''));
            $observaciones = $this->cleanUtf8(trim($fields['field_4'] ?? ''));
            // Quien cargo la solicitud en SharePoint (viene ya resuelto en el lote)
            $autorSp = $this->extractItemAuthor($item);

            $solicitanteSp = $syncRequestedBy
                ? $this->cleanUtf8($this->extractRequestedByValue(
                    $fields[$spFieldReqBy] ?? ($fields[$spFieldReqBy . 'LookupId'] ?? '')
                  ))
                : '';
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

            // 4. FECHA Y HORA DE SOLICITUD
            // La hora de la solicitud es un dato del negocio: la escribe el sistema en
            // field_6 y NO debe reemplazarse por la hora de creación del elemento en
            // SharePoint. Antes se tomaba sólo la fecha de field_6 y la hora se
            // sobrescribía con createdDateTime, por lo que cualquier sincronización
            // posterior a una edición borraba la hora registrada por el usuario.
            $spCreated = !empty($item['createdDateTime']) ? $item['createdDateTime'] : ($fields['Created'] ?? null);
            $rawField6 = isset($fields['field_6']) ? trim((string)$fields['field_6']) : '';

            // Fecha (YYYY-MM-DD) tal como viene en field_6, sin desplazamiento UTC
            $field6Date = '';
            if ($rawField6 !== '' && preg_match('/^(\d{4}-\d{2}-\d{2})/', $rawField6, $mDate6)) {
                $field6Date = $mDate6[1];
            }

            // Una columna de SOLO FECHA llega siempre en punto (00:00:00Z, u 08:00:00Z
            // si el sitio esta en otra zona horaria). Solo se considera hora real la
            // que trae minutos o segundos, que es la que escribe esta aplicacion.
            $field6HasRealTime = ($rawField6 !== ''
                && preg_match('/T(\d{2}:\d{2}:\d{2})/', $rawField6, $mTime6)
                && $this->esHoraReal($mTime6[1]));

            if ($field6HasRealTime) {
                $createdAt = date('Y-m-d H:i:s', strtotime($rawField6));
            } else {
                // field_6 no aporta hora: conservar la que ya tiene el registro local.
                $preservedTime = null;

                // 1) La hora que ya tenga el registro local, si es una hora real.
                //    Una hora exacta en punto procede de una sincronizacion previa
                //    y no debe perpetuarse.
                if (!empty($localCreatedAt)) {
                    $tsLocal = strtotime($localCreatedAt);
                    if ($tsLocal) {
                        $candidate = date('H:i:s', $tsLocal);
                        if ($this->esHoraReal($candidate)) {
                            $preservedTime = $candidate;
                        }
                    }
                }

                // 2) Si no, la hora en que el elemento se creo en SharePoint:
                //    es el momento real en que se cargo la solicitud.
                if ($preservedTime === null && !empty($spCreated)) {
                    $candidate = date('H:i:s', strtotime($spCreated));
                    if ($candidate !== '00:00:00') {
                        $preservedTime = $candidate;
                    }
                }

                // 3) Ultimo recurso: inicio de jornada.
                if ($preservedTime === null) {
                    $preservedTime = '08:00:00';
                }

                $baseDate = $field6Date;
                if ($baseDate === '') {
                    if (!empty($localCreatedAt)) {
                        $baseDate = date('Y-m-d', strtotime($localCreatedAt));
                    } elseif (!empty($spCreated)) {
                        $baseDate = date('Y-m-d', strtotime($spCreated));
                    } else {
                        $baseDate = date('Y-m-d');
                    }
                }

                $createdAt = "{$baseDate} {$preservedTime}";
            }

            $dueDate = null;
            if (!empty($fields['field_7'])) {
                $rawField7Date = substr($fields['field_7'], 0, 10);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawField7Date)) {
                    $dueDate = "{$rawField7Date} 18:00:00";
                } else {
                    $dueDate = date('Y-m-d H:i:s', strtotime($fields['field_7']));
                    if (str_ends_with($dueDate, '00:00:00')) {
                        $dueDate = substr($dueDate, 0, 10) . ' 18:00:00';
                    }
                }
            }

            $resolvedAt = null;
            if ($statusId === 4) {
                if (!empty($fields['field_8'])) {
                    $rawField8Date = substr($fields['field_8'], 0, 10);
                    $modTime = !empty($item['lastModifiedDateTime']) ? date('H:i:s', strtotime($item['lastModifiedDateTime'])) : date('H:i:s');
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawField8Date)) {
                        $resolvedAt = "{$rawField8Date} {$modTime}";
                    } else {
                        $resolvedAt = date('Y-m-d H:i:s', strtotime($fields['field_8']));
                    }
                } elseif (!empty($item['lastModifiedDateTime'])) {
                    $resolvedAt = date('Y-m-d H:i:s', strtotime($item['lastModifiedDateTime']));
                } else {
                    $resolvedAt = date('Y-m-d H:i:s');
                }
                if ($resolvedAt && str_ends_with($resolvedAt, '00:00:00') && !empty($item['lastModifiedDateTime'])) {
                    $resolvedAt = substr($resolvedAt, 0, 10) . ' ' . date('H:i:s', strtotime($item['lastModifiedDateTime']));
                }
            }

            // 5. Agente Asignado (Mapeo directo con lo que viene en 'Responsable' de SharePoint)
            $assignedAgentId = null;
            if (!empty($responsable)) {
                static $agentUserCache = [];
                if (!isset($agentUserCache[$responsable])) {
                    $agentEmailSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $responsable));
                    if (empty($agentEmailSlug)) $agentEmailSlug = 'agente';
                    $agentEmail = "{$agentEmailSlug}@empresa.com";

                    $stmtFindAgent = $this->db->prepare("
                        SELECT id FROM users 
                        WHERE LOWER(TRIM(alias)) = LOWER(:resp_alias) 
                           OR LOWER(TRIM(name)) = LOWER(:resp_name) 
                        LIMIT 1
                    ");
                    $stmtFindAgent->execute([
                        ':resp_alias' => $responsable,
                        ':resp_name'  => $responsable
                    ]);
                    $existingAgent = $stmtFindAgent->fetch();

                    if ($existingAgent) {
                        $agentUserCache[$responsable] = (int)$existingAgent['id'];
                    } else {
                        // Crear usuario agente con el alias/siglas de SharePoint en nombre y alias
                        $stmtCreateAgent = $this->db->prepare("
                            INSERT INTO users (area_id, role_id, name, alias, email, password_hash, is_active)
                            VALUES (:area_id, 3, :name, :alias, :email, :pass, 1)
                        ");
                        $defaultPass = '$2y$10$qV4eW4vR6p0yR8.XQ24.m0d1FqWuu1.Yy3gE7wO0a123456789012';
                        $stmtCreateAgent->execute([
                            ':area_id' => $areaId,
                            ':name'    => $responsable,
                            ':alias'   => $responsable,
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

            // Ejecución UPSERT ($exists ya se resolvió al inicio de la iteración)
            $compNameClean = !empty($empresa) ? $empresa : 'Empresa General';
            $areaCodeClean = !empty($areaStr) ? $areaStr : ($areaId === 3 ? 'SSGG' : 'IT');
            // Un responsable vacio en SharePoint NO equivale a desasignar: puede ser
            // que la asignacion se hiciera desde esta aplicacion y aun no viaje a
            // SharePoint. Se pasa null para que COALESCE conserve el valor local.
            $responsableSp = trim((string)$responsable);
            $responsableEsReal = ($responsableSp !== '' && strcasecmp($responsableSp, 'Sin Asignar') !== 0);
            $responsableClean = $responsableEsReal ? $responsableSp : null;

            // Si SharePoint no aporta responsable, tampoco se toca el agente local.
            if (!$responsableEsReal) {
                $assignedAgentId = null;
            }
            $spStatusClean = !empty($estatusStr) ? $estatusStr : 'Pendiente';

            // Solo se acepta un nombre real. Un valor vacio o puramente numerico
            // (un LookupId sin resolver) se descarta pasando null, de modo que el
            // solicitante guardado en el sistema queda intacto.
            $solicitanteUsable = ($solicitanteSp !== '' && !ctype_digit($solicitanteSp));
            $reqByParam = $syncRequestedBy
                ? [':requested_by' => ($solicitanteUsable ? $solicitanteSp : null)]
                : [];

            $autorParam = [
                ':sp_created_by'       => $autorSp['name'],
                ':sp_created_by_email' => $autorSp['email'],
            ];

            if ($exists) {
                $stmtUpdate->execute($reqByParam + $autorParam + [
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
                    ':created_at'        => $createdAt,
                    ':sp_id'             => $spId,
                ]);
                $updated++;
            } else {
                $stmtInsert->execute($reqByParam + $autorParam + [
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

        $message = "Sincronización completada: {$synced} tickets procesados ({$created} nuevos, {$updated} actualizados)." . $requestedByNotice;
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
     * Obtener el catálogo oficial y dinámico de empresas desde SharePoint (Choices del campo field_0)
     * unificado con todos los registros históricos de la base de datos local.
     */
    public function getCompanyCatalog(): array {
        static $cachedCompanies = null;
        if ($cachedCompanies !== null) {
            return $cachedCompanies;
        }

        // 1. Valores base conocidos configurados en la lista de SharePoint
        $companies = [
            'Inveca',
            'Kaizen Financial Group',
            'Kaizen Holdings',
            'Socado'
        ];

        // 2. Consultar choices oficiales del campo field_0 ("EMPRESA") en Microsoft Graph
        try {
            $token = $this->getAccessToken();
            $listId = $this->getListId($token);
            $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/lists/{$listId}/columns/field_0";

            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer {$token}",
                    "Accept: application/json"
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 6);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                $resp = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode >= 200 && $httpCode < 300 && !empty($resp)) {
                    $colData = json_decode($resp, true);
                    if (!empty($colData['choice']['choices']) && is_array($colData['choice']['choices'])) {
                        foreach ($colData['choice']['choices'] as $chName) {
                            $chName = trim($chName);
                            if ($chName !== '' && !in_array($chName, $companies, true)) {
                                $companies[] = $chName;
                            }
                        }
                    }
                }
            } else {
                $opts = [
                    'http' => [
                        'method'  => 'GET',
                        'header'  => "Authorization: Bearer {$token}\r\nAccept: application/json\r\n",
                        'timeout' => 6,
                        'ignore_errors' => true
                    ],
                    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
                ];
                $res = @file_get_contents($url, false, stream_context_create($opts));
                if ($res) {
                    $colData = json_decode($res, true);
                    if (!empty($colData['choice']['choices']) && is_array($colData['choice']['choices'])) {
                        foreach ($colData['choice']['choices'] as $chName) {
                            $chName = trim($chName);
                            if ($chName !== '' && !in_array($chName, $companies, true)) {
                                $companies[] = $chName;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {}

        // 3. Obtener todas las empresas presentes en los tickets de la BD local sin filtros
        try {
            if ($this->db) {
                $stmt = $this->db->query("
                    SELECT DISTINCT TRIM(company_name) as comp 
                    FROM tickets 
                    WHERE company_name IS NOT NULL AND TRIM(company_name) != ''
                ");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $c = trim($row['comp'] ?? '');
                    if ($c !== '' && !in_array($c, $companies, true)) {
                        $companies[] = $c;
                    }
                }
            }
        } catch (\Throwable $e) {}

        // 4. Limpiar, deduplicar y ordenar alfabéticamente
        $companies = array_unique(array_filter(array_map('trim', $companies)));
        sort($companies, SORT_NATURAL | SORT_FLAG_CASE);

        $cachedCompanies = array_values($companies);
        return $cachedCompanies;
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

            // Solicitante: solo si la columna esta mapeada explicitamente en el .env.
            // Soporta columnas de texto y de tipo "Persona o grupo".
            if ($this->fieldRequestedBy !== '' && !empty($data['requested_by'])) {
                $fieldsPayload += $this->buildRequestedByPayload((string)$data['requested_by']);
            }

            // Fecha de Solicitud (field_6)
            $reqDate = !empty($data['request_date']) ? strtotime($data['request_date']) : time();
            $fieldsPayload['field_6'] = gmdate('Y-m-d\TH:i:s\Z', $reqDate);

            // Fecha Propuesta (field_7)
            if (!empty($data['due_date'])) {
                $fieldsPayload['field_7'] = gmdate('Y-m-d\TH:i:s\Z', strtotime($data['due_date']));
            } else {
                $fieldsPayload['field_7'] = gmdate('Y-m-d\TH:i:s\Z', $reqDate);
            }

            // Fecha de Cierre si ya viene Procesado (field_8)
            if ($fieldsPayload['field_5'] === 'Procesado') {
                $fieldsPayload['field_8'] = gmdate('Y-m-d\TH:i:s\Z');
            }

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
        // Modo seguro: igual que crear y eliminar, ninguna escritura llega a
        // produccion mientras SHAREPOINT_DRY_RUN sea "true".
        $isDryRun = (getenv('SHAREPOINT_DRY_RUN') === 'true') || (!empty($updatedFields['dry_run']));
        if ($isDryRun) {
            return [
                'success' => true,
                'dry_run' => true,
                'message' => "[Modo Seguro / Dry-Run] Cambios validados sobre el item #{$itemId} sin modificar SharePoint."
            ];
        }

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
            if ($this->fieldRequestedBy !== '' && array_key_exists('requested_by', $updatedFields)) {
                $fieldsPayload += $this->buildRequestedByPayload((string)$updatedFields['requested_by']);
            }
            if (array_key_exists('observations', $updatedFields)) {
                $fieldsPayload['field_4'] = (string)$updatedFields['observations'];
            }
            if (isset($updatedFields['sp_status'])) {
                $fieldsPayload['field_5'] = trim((string)$updatedFields['sp_status']);
                if ($fieldsPayload['field_5'] === 'Procesado') {
                    $fieldsPayload['field_8'] = gmdate('Y-m-d\TH:i:s\Z');
                }
            }

            // Fecha de Solicitud (field_6)
            if (!empty($updatedFields['request_date'])) {
                $fieldsPayload['field_6'] = gmdate('Y-m-d\TH:i:s\Z', strtotime($updatedFields['request_date']));
            }

            // Fecha Propuesta (field_7)
            if (array_key_exists('due_date', $updatedFields)) {
                if (!empty($updatedFields['due_date'])) {
                    $fieldsPayload['field_7'] = gmdate('Y-m-d\TH:i:s\Z', strtotime($updatedFields['due_date']));
                } else {
                    $fieldsPayload['field_7'] = null;
                }
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
     * Utiliza cURL de forma prioritaria con fallback a stream context,
     * garantizando compatibilidad con entornos Windows / XAMPP / Docker / CLI.
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

        if ($itemId <= 0) {
            return [
                'success' => false,
                'message' => "ID de registro de SharePoint inválido (#{$itemId})."
            ];
        }

        try {
            $token = $this->getAccessToken();
            $listId = $this->getListId($token);
            $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/lists/{$listId}/items/{$itemId}";

            $response = '';
            $httpCode = 0;
            $maxAttempts = 3;

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                if (function_exists('curl_init')) {
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        "Authorization: Bearer {$token}",
                        "Accept: application/json"
                    ]);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlErr = curl_error($ch);
                    curl_close($ch);

                    if ($httpCode === 0 && !empty($curlErr)) {
                        throw new Exception("Error cURL de conexión con Microsoft Graph: {$curlErr}");
                    }
                } else {
                    $opts = [
                        'http' => [
                            'method'        => 'DELETE',
                            'header'        => "Authorization: Bearer {$token}\r\nAccept: application/json\r\n",
                            'timeout'       => 20,
                            'ignore_errors' => true
                        ],
                        'ssl' => [
                            'verify_peer'      => false,
                            'verify_peer_name' => false
                        ]
                    ];

                    $context = stream_context_create($opts);
                    $response = @file_get_contents($url, false, $context);

                    $statusLine = $http_response_header[0] ?? '';
                    preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $match);
                    $httpCode = isset($match[1]) ? (int)$match[1] : 0;
                }

                // Si es un error transitorio de SharePoint (503 Service Unavailable, 429 Throttling, 504 Gateway Timeout), reintentar brevemente
                if (in_array($httpCode, [503, 429, 504], true) && $attempt < $maxAttempts) {
                    usleep(700000); // 700ms de espera
                    continue;
                }

                break;
            }

            if ($httpCode === 204 || ($httpCode >= 200 && $httpCode < 300)) {
                return [
                    'success'   => true,
                    'http_code' => $httpCode,
                    'item_id'   => $itemId,
                    'message'   => "Registro #{$itemId} eliminado exitosamente en SharePoint."
                ];
            }

            if ($httpCode === 404) {
                return [
                    'success'   => true,
                    'http_code' => 404,
                    'item_id'   => $itemId,
                    'message'   => "El registro #{$itemId} ya no existía en SharePoint o había sido eliminado previamente."
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
                'item_id'   => $itemId,
                'message'   => "Error en SharePoint (HTTP {$httpCode}): {$errorMsg}"
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'item_id' => $itemId,
                'message' => 'Excepción al eliminar en SharePoint: ' . $e->getMessage()
            ];
        }
    }
}

