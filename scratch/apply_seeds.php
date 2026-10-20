<?php
require __DIR__ . '/../config/database.php';
$pdo = Config\Database::getConnection();

$sql = file_get_contents(__DIR__ . '/../sql/seeds.sql');
$pdo->exec($sql);

echo "Seeds applied successfully.\n";
