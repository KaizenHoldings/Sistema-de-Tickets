<?php
// Test Role-Based Views and Dynamic Assign Button Logic

function testUserSession($email, $password) {
    $cookieFile = __DIR__ . '/cookie_' . md5($email) . '.txt';
    if (file_exists($cookieFile)) unlink($cookieFile);

    $ch = curl_init('http://localhost:8080/sistema-tickets/public/api.php?action=login');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => $email, 'password' => $password]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $login = json_decode(curl_exec($ch), true);
    curl_close($ch);

    $ch = curl_init('http://localhost:8080/sistema-tickets/public/api.php?action=get_all_tickets');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $tickets = json_decode(curl_exec($ch), true);
    curl_close($ch);

    $ch = curl_init('http://localhost:8080/sistema-tickets/public/index.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $html = curl_exec($ch);
    curl_close($ch);

    $hasAsignar = strpos($html, '👤 Asignar Agente') !== false;
    $hasReasignar = strpos($html, '🔄 Reasignar Agente') !== false;

    return [
        'user' => $login['user'] ?? null,
        'ticket_count' => count($tickets['data'] ?? []),
        'has_asignar_btn' => $hasAsignar,
        'has_reasignar_btn' => $hasReasignar,
    ];
}

echo "=== SUPERADMIN ===\n";
print_r(testUserSession('superadmin@empresa.com', 'password123'));

echo "\n=== COORDINADOR TI (MANAGER) ===\n";
print_r(testUserSession('carlos.mendoza@empresa.com', 'password123'));

echo "\n=== SOLICITANTE (USER) ===\n";
print_r(testUserSession('ana.lopez@empresa.com', 'password123'));
