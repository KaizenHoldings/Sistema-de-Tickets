<?php
// Redireccionar al punto de entrada público conservando parámetros GET
$qs = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
header('Location: public/index.php' . $qs);
exit;
