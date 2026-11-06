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
            transition: transform 0.2s ease;
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
        .login-alert-box {
            display: none;
            align-items: flex-start;
            gap: 0.65rem;
            background-color: #FEF2F2;
            border: 1px solid #FECACA;
            border-left: 4px solid #EF4444;
            color: #991B1B;
            font-size: 0.85rem;
            font-weight: 500;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            line-height: 1.4;
            animation: fadeInDown 0.25s ease-out;
        }
        .login-alert-icon {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            margin-top: 1px;
            color: #EF4444;
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .shake-card {
            animation: shake 0.4s ease-in-out;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-6px); }
            40%, 80% { transform: translateX(6px); }
        }
    </style>
</head>
<body>

    <div class="login-card" id="loginCard">
        <div class="login-brand">
            <div class="login-brand-icon">K</div>
            <span>Kaizen ServiceDesk</span>
        </div>
        <p style="text-align: center; color: var(--fd-text-muted); font-size: 0.88rem; margin-bottom: 1.5rem;">
            Servicio de asistencia técnica : Kaizen
        </p>

        <!-- Alerta visual integrada y amigable (sin popups de navegador) -->
        <div id="login-alert-box" class="login-alert-box" role="alert">
            <svg class="login-alert-icon" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/>
            </svg>
            <div id="login-alert-msg" style="flex:1;">Credenciales incorrectas. Verifique su correo y contraseña.</div>
        </div>

        <form id="login-form">
            <div class="form-group">
                <label for="email" style="font-weight:600; font-size:13px; color:#1E293B;">Correo Electrónico:</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="usuario@empresa.com" required style="padding:0.6rem 0.85rem;">
            </div>

            <div class="form-group">
                <label for="password" style="font-weight:600; font-size:13px; color:#1E293B;">Contraseña:</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required style="padding:0.6rem 0.85rem;">
            </div>

            <button type="submit" id="btn-submit" class="btn-primary" style="width: 100%; padding:0.7rem; font-size:14px; margin-top: 0.5rem; justify-content:center; transition: all 0.2s;">
                Ingresar al Sistema
            </button>
        </form>
    </div>

    <script>
        const loginForm = document.getElementById('login-form');
        const loginCard = document.getElementById('loginCard');
        const alertBox = document.getElementById('login-alert-box');
        const alertMsg = document.getElementById('login-alert-msg');
        const btnSubmit = document.getElementById('btn-submit');
        const passInput = document.getElementById('password');

        function showError(message) {
            alertMsg.textContent = message || 'Credenciales incorrectas. Por favor verifique sus datos.';
            alertBox.style.display = 'flex';
            loginCard.classList.remove('shake-card');
            void loginCard.offsetWidth; // Forzar reflow para reiniciar animación
            loginCard.classList.add('shake-card');
            passInput.value = '';
            passInput.focus();
        }

        function hideError() {
            alertBox.style.display = 'none';
        }

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('email').value.trim();
            const password = passInput.value;
            if (!email || !password) return;

            hideError();
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = 'Verificando credenciales...';

            try {
                const response = await fetch('api.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password })
                });

                const result = await response.json();
                if (result.success) {
                    btnSubmit.innerHTML = '✓ Acceso concedido';
                    btnSubmit.style.backgroundColor = '#10B981';
                    window.location.href = 'index.php';
                } else {
                    showError(result.message || 'Credenciales incorrectas. Verifique su correo y contraseña.');
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = 'Ingresar al Sistema';
                }
            } catch (e) {
                showError('Error de comunicación con el servidor. Intente nuevamente.');
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = 'Ingresar al Sistema';
            }
        });
    </script>
</body>
</html>
