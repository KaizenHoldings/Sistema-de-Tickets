<?php
/**
 * Inspector de columnas de la lista de SharePoint — OPERACIÓN DE SOLO LECTURA.
 *
 * Imprime el nombre interno (el que se usa en el código y en el .env), el
 * nombre visible y el tipo de cada columna de la lista configurada.
 *
 * Úsalo para identificar con certeza la columna del SOLICITANTE antes de
 * habilitar SHAREPOINT_FIELD_REQUESTED_BY, y así no escribir nunca en una
 * columna equivocada de la lista de producción.
 *
 * Uso:
 *   php bin/list_sharepoint_columns.php
 *   php bin/list_sharepoint_columns.php solicit     # filtra por texto
 *
 * Dentro de Docker:
 *   docker compose exec app php bin/list_sharepoint_columns.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Services/SharePointService.php';

$filter = isset($argv[1]) ? mb_strtolower(trim($argv[1])) : '';

$service = new \Services\SharePointService();
$result = $service->getListColumns();

if (empty($result['success'])) {
    fwrite(STDERR, "No se pudo leer el esquema de la lista: " . ($result['message'] ?? 'error desconocido') . PHP_EOL);
    exit(1);
}

echo PHP_EOL;
echo "Lista: " . ($result['list_name'] ?? '(sin nombre)') . PHP_EOL;
echo "Columnas totales: " . ($result['total'] ?? 0) . PHP_EOL;

$mapped = $result['mapped_field'] ?? '';
echo "Columna mapeada al solicitante: " . ($mapped !== '' ? $mapped : '(ninguna — integración desactivada)') . PHP_EOL;
if ($filter !== '') {
    echo "Filtro aplicado: \"{$filter}\"" . PHP_EOL;
}
echo PHP_EOL;

printf("%-22s %-40s %-14s %s" . PHP_EOL, 'NOMBRE INTERNO', 'NOMBRE VISIBLE', 'TIPO', 'NOTAS');
echo str_repeat('-', 100) . PHP_EOL;

$shown = 0;
foreach ($result['columns'] as $col) {
    if ($filter !== '') {
        $haystack = mb_strtolower($col['name'] . ' ' . $col['display_name']);
        if (!str_contains($haystack, $filter)) {
            continue;
        }
    }

    $notes = [];
    if (!empty($col['read_only'])) $notes[] = 'solo lectura';
    if (!empty($col['hidden']))    $notes[] = 'oculta';
    if ($mapped !== '' && $col['name'] === $mapped) $notes[] = '<== MAPEADA AL SOLICITANTE';

    printf(
        "%-22s %-40s %-14s %s" . PHP_EOL,
        $col['name'],
        mb_strimwidth($col['display_name'], 0, 40, '...'),
        $col['type'],
        implode(', ', $notes)
    );
    $shown++;
}

echo str_repeat('-', 100) . PHP_EOL;
echo "{$shown} columna(s) mostrada(s)." . PHP_EOL . PHP_EOL;

echo "Para habilitar la sincronización del solicitante, copia el NOMBRE INTERNO" . PHP_EOL;
echo "de la columna correcta (que NO sea de solo lectura) en el .env:" . PHP_EOL . PHP_EOL;
echo "    SHAREPOINT_FIELD_REQUESTED_BY=field_X" . PHP_EOL . PHP_EOL;

$mappedType = $result["mapped_field_type"] ?? "";
if ($mappedType !== "") {
    echo "Tipo de la columna mapeada actualmente: {$mappedType}" . PHP_EOL;
}

echo "Tipos soportados:" . PHP_EOL;
echo "  text          -> se escribe el nombre tal cual." . PHP_EOL;
echo "  personOrGroup -> columna de Persona (la que pide \"un nombre o un correo\")." . PHP_EOL;
echo "                   El sistema resuelve el nombre o correo contra los usuarios" . PHP_EOL;
echo "                   del sitio y escribe su LookupId. El texto debe coincidir con" . PHP_EOL;
echo "                   el nombre completo del directorio, o ser el correo." . PHP_EOL . PHP_EOL;
