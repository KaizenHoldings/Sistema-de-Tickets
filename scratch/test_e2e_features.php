<?php
// E2E Verification Script for Status Changes, Mandatory Note, CSAT and Dynamic Assignment

$cookieFile = __DIR__ . '/cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

function httpPost($url, $data, $cookieFile, $csrfToken = '') {
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json'];
    if ($csrfToken) $headers[] = 'X-CSRF-Token: ' . $csrfToken;
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function httpGet($url, $cookieFile) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

echo "=== 1. TEST LOGIN SUPERADMIN ===\n";
$loginRes = httpPost('http://localhost:8080/sistema-tickets/public/api.php?action=login', [
    'email' => 'superadmin@empresa.com',
    'password' => 'password123'
], $cookieFile);
echo "Login result: " . json_encode($loginRes) . "\n";

echo "\n=== 2. GET CSRF TOKEN FROM INDEX ===\n";
$html = httpGet('http://localhost:8080/sistema-tickets/public/index.php', $cookieFile);
preg_match('/window\.CSRF_TOKEN = "([a-f0-9]+)"/', $html, $matches);
$csrfToken = $matches[1] ?? '';
echo "Extracted CSRF token: " . ($csrfToken ? "OK ($csrfToken)" : "NOT FOUND") . "\n";

echo "\n=== 3. TEST STATUS CHANGE WITHOUT COMMENT (SHOULD BE REJECTED) ===\n";
$failRes = httpPost('http://localhost:8080/sistema-tickets/public/api.php?action=update_kanban_status', [
    'ticket_id' => 1,
    'status_id' => 2,
    'comment' => '',
    'csrf_token' => $csrfToken
], $cookieFile, $csrfToken);
echo "Result without comment: " . json_encode($failRes) . "\n";

echo "\n=== 4. TEST STATUS CHANGE WITH MANDATORY NOTE (SHOULD SUCCEED) ===\n";
$okRes = httpPost('http://localhost:8080/sistema-tickets/public/api.php?action=update_kanban_status', [
    'ticket_id' => 1,
    'status_id' => 2,
    'comment' => 'Iniciando investigación del corte de fibra óptica en nodo central.',
    'is_internal' => true,
    'csrf_token' => $csrfToken
], $cookieFile, $csrfToken);
echo "Result with internal note: " . json_encode($okRes) . "\n";

echo "\n=== 5. RESOLVE TICKET (STATUS 4) WITH COMMENT ===\n";
$resolveRes = httpPost('http://localhost:8080/sistema-tickets/public/api.php?action=update_kanban_status', [
    'ticket_id' => 1,
    'status_id' => 4,
    'comment' => 'Empalme de fibra óptica finalizado con éxito. Conectividad 100% restablecida.',
    'is_internal' => false,
    'csrf_token' => $csrfToken
], $cookieFile, $csrfToken);
echo "Result resolve: " . json_encode($resolveRes) . "\n";

echo "\n=== 6. SUBMIT CSAT SATISFACTION SURVEY ===\n";
$csatRes = httpPost('http://localhost:8080/sistema-tickets/public/api.php?action=submit_csat', [
    'ticket_id' => 1,
    'rating' => 5,
    'feedback' => 'Excelente tiempo de respuesta y atención impecable por parte del equipo técnico.',
    'csrf_token' => $csrfToken
], $cookieFile, $csrfToken);
echo "CSAT submit result: " . json_encode($csatRes) . "\n";

echo "\n=== 7. VERIFY TICKET DETAIL WITH SURVEY AND COMMENTS ===\n";
$detailRes = json_decode(httpGet('http://localhost:8080/sistema-tickets/public/api.php?action=get_ticket_detail&ticket_id=1', $cookieFile), true);
echo "Ticket Code: " . ($detailRes['data']['code'] ?? '') . "\n";
echo "Ticket Status: " . ($detailRes['data']['status_name'] ?? '') . " (ID: " . ($detailRes['data']['status_id'] ?? '') . ")\n";
echo "Ticket Agent: " . ($detailRes['data']['agent_name'] ?? 'Sin Asignar') . "\n";
echo "Has CSAT Survey: " . (!empty($detailRes['data']['survey']) ? 'YES (Rating: ' . $detailRes['data']['survey']['rating'] . '/5)' : 'NO') . "\n";

echo "\n=== 8. CHECK COMMENTS COUNT ===\n";
$commentsRes = json_decode(httpGet('http://localhost:8080/sistema-tickets/public/api.php?action=get_comments&ticket_id=1', $cookieFile), true);
echo "Total comments recorded for Ticket #1: " . count($commentsRes['data'] ?? []) . "\n";
foreach ($commentsRes['data'] as $c) {
    echo " - [" . ($c['is_internal'] ? '🔒 NOTA INTERNA' : '💬 COMENTARIO') . "] " . $c['user_name'] . ": " . substr($c['comment'], 0, 60) . "...\n";
}
