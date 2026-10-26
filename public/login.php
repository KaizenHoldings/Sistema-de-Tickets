<?php
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
ini_set('default_charset', 'UTF-8');

session_start();

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';

// Asegurar superadmin desde variables de entorno
\Controllers\UserController::ensureSuperAdminExists();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Servicio de asistencia técnica : Kaizen</title>
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #F3F5F8;
            font-family: 'Inter', system-ui, sans-serif;
            padding: 1.5rem;
        }
        .login-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 2.5rem;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.06);
        }
        .login-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            font-size: 1.5rem;
            font-weight: 700;
            color: #12344D;
            margin-bottom: 0.35rem;
        }
        .login-brand-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #10B981, #0284C7);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 1.25rem;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-brand">
            <div class="login-brand-icon">K</div>
            <span>Kaizen ServiceDesk</span>
        </div>
        <p style="text-align: center; color: var(--fd-text-muted); font-size: 0.88rem; margin-bottom: 1.75rem;">
            Servicio de asistencia técnica : Kaizen
        </p>

        <form id="login-form">
            <div class="form-group">
                <label for="email" style="font-weight:600; font-size:13px; color:#1E293B;">Correo Electrónico:</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="usuario@empresa.com" required style="padding:0.6rem 0.85rem;">
            </div>

            <div class="form-group">
                <label for="password" style="font-weight:600; font-size:13px; color:#1E293B;">Contraseña:</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required style="padding:0.6rem 0.85rem;">
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; padding:0.7rem; font-size:14px; margin-top: 0.5rem; justify-content:center;">
                Ingresar al Sistema
            </button>
        </form>
    </div>

    <script>
        document.getElementById('login-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            executeLogin(email, password);
        });

        async function executeLogin(email, password) {
            try {
                const response = await fetch('api.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password })
                });

                const result = await response.json();
                if (result.success) {
                    window.location.href = 'index.php';
                } else {
                    alert(result.message || 'Credenciales incorrectas');
                }
            } catch (e) {
                alert('Error al conectar con el servidor.');
            }
        }
    </script>
</body>
</html>
