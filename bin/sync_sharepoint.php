<?php
/**
 * Script CLI de Sincronización de Tickets de SharePoint -> MySQL
 * Uso: php bin/sync_sharepoint.php [limite]
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Services/SharePointService.php';

use Services\SharePointService;

echo "========================================================\n";
echo "   Sincronización de Tickets de SharePoint a MySQL      \n";
echo "========================================================\n";

$limit = isset($argv[1]) ? (int)$argv[1] : 300;
echo "Límite de solicitudes a procesar: {$limit}\n";
echo "Conectando a Microsoft Graph API...\n";

try {
    $service = new SharePointService();
    $result = $service->syncToDatabase($limit);

    if ($result['success']) {
        echo "\n[OK] {$result['message']}\n";
        echo "- Total obtenidos de SharePoint: {$result['total_fetched']}\n";
        echo "- Nuevos insertados: {$result['created']}\n";
        echo "- Actualizados: {$result['updated']}\n";
    } else {
        echo "\n[ERROR] No se pudo completar la sincronización.\n";
    }
} catch (Exception $e) {
    echo "\n[EXCEPCIÓN] " . $e->getMessage() . "\n";
    exit(1);
}

echo "========================================================\n";
exit(0);
