<?php
session_start();

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Servicio de asistencia técnica : Kaizen</title>
    <link rel="stylesheet" href="css/style.css">
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
            max-width: 480px;
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
        .demo-roles {
            margin-top: 1.75rem;
            padding-top: 1.25rem;
            border-top: 1px solid #EDF2F7;
        }
        .demo-btn {
            background: #F8FAFC;
            color: #1E293B;
            border: 1px solid #CBD5E1;
            padding: 0.65rem 0.9rem;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            width: 100%;
            text-align: left;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.15s;
        }
        .demo-btn:hover {
            background: #EFF6FF;
            border-color: #2563EB;
            color: #1D4ED8;
            transform: translateY(-1px);
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
                <input type="password" id="password" name="password" class="form-control" value="password123" required style="padding:0.6rem 0.85rem;">
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; padding:0.7rem; font-size:14px; margin-top: 0.5rem; justify-content:center;">
                Ingresar al Sistema
            </button>
        </form>

        <!-- ACCESO RÁPIDO PARA VALIDACIÓN DE ROLES (QA) -->
        <div class="demo-roles">
            <h4 style="font-size: 0.78rem; color: #64748B; margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing:0.5px; font-weight:700;">
                🔑 Acceso Rápido por Perfil (3 Roles Oficiales):
            </h4>

            <button class="demo-btn" onclick="quickLogin('superadmin@empresa.com')">
                <span>👑 <strong>Superadministrador</strong> (Acceso total & ajustes de todas las áreas)</span>
                <small style="color:#2563EB; font-weight:600;">Entrar &rarr;</small>
            </button>

            <button class="demo-btn" onclick="quickLogin('carlos.mendoza@empresa.com')">
                <span>👔 <strong>Coordinador</strong> (Crea, consulta, asigna & atiende de su área)</span>
                <small style="color:#2563EB; font-weight:600;">Entrar &rarr;</small>
            </button>

            <button class="demo-btn" onclick="quickLogin('juan.perez@empresa.com')">
                <span>🛠️ <strong>Colaborador</strong> (Crea, consulta & atiende asignados y de su área)</span>
                <small style="color:#2563EB; font-weight:600;">Entrar &rarr;</small>
            </button>
        </div>
    </div>

    <script>
        document.getElementById('login-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            executeLogin(email, password);
        });

        function quickLogin(email) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = 'password123';
            executeLogin(email, 'password123');
        }

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
