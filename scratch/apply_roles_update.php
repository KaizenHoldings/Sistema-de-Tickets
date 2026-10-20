<?php
require __DIR__ . '/../config/database.php';
$pdo = Config\Database::getConnection();

// 1. Actualizar nombres y descripciones exactas de los 3 roles
$pdo->exec("
    UPDATE roles SET name = 'admin', description = 'Superadministrador' WHERE id = 1;
    UPDATE roles SET name = 'manager', description = 'Coordinador' WHERE id = 2;
    UPDATE roles SET name = 'colaborador', description = 'Colaborador' WHERE id = 3;
");

// 2. Asegurar que los usuarios asignados al rol 3 tengan su rol correcto
$pdo->exec("UPDATE users SET role_id = 3 WHERE id IN (5, 6, 7, 8, 9);");

echo "Roles updated successfully in database.\n";
print_r($pdo->query("SELECT * FROM roles")->fetchAll(PDO::FETCH_ASSOC));
print_r($pdo->query("SELECT u.id, u.name, u.email, r.name as role_name, r.description as role_desc, a.name as area_name FROM users u JOIN roles r ON u.role_id = r.id LEFT JOIN areas a ON u.area_id = a.id")->fetchAll(PDO::FETCH_ASSOC));
