-- =============================================================================
-- SEEDS UNIFICADOS ENTERPRISE CON DATOS REALISTAS PARA PRUEBAS QA & RBAC
-- =============================================================================

USE service_desk_db;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE ticket_comments;
TRUNCATE TABLE ticket_audit_logs;
TRUNCATE TABLE sla_policies;
TRUNCATE TABLE tickets;
TRUNCATE TABLE users;
TRUNCATE TABLE areas;
TRUNCATE TABLE kanban_status;
TRUNCATE TABLE roles;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. MODELO DE 3 ROLES
INSERT INTO roles (id, name, description) VALUES 
(1, 'admin', 'Superadministrador'),
(2, 'manager', 'Coordinador'),
(3, 'colaborador', 'Colaborador');

-- 2. ESTADOS KANBAN / FRESHDESK
INSERT INTO kanban_status (id, name, slug, sort_order, is_final) VALUES 
(1, 'Por Asignar', 'unassigned', 1, 0),
(2, 'En Progreso', 'in_progress', 2, 0),
(3, 'Pendiente Usuario', 'pending_user', 3, 0),
(4, 'Resuelto', 'resolved', 4, 1);

-- 3. DEPARTAMENTOS / ÁREAS
INSERT INTO areas (id, name, description, manager_id) VALUES 
(1, 'Tecnología e Informática (TI)', 'Soporte técnico, redes, infraestructura, servidores y telecomunicaciones.', NULL),
(2, 'Recursos Humanos (RRHH)', 'Gestión del talento humano, nómina, permisos y certificaciones.', NULL),
(3, 'Mantenimiento y Servicios Generales', 'Infraestructura física, climatización, fontanería y electricidad.', NULL),
(4, 'Finanzas y Contabilidad', 'Sistemas contables, Profit Plus, facturación y reembolsos.', NULL);

-- 4. USUARIOS DE PRUEBA (Contraseña universal: password123)
SET @pass = '$2y$10$qV4eW4vR6p0yR8.XQ24.m0d1FqWuu1.Yy3gE7wO0a123456789012';

INSERT INTO users (id, area_id, role_id, name, email, password_hash, is_active) VALUES 
(1, NULL, 1, 'SuperAdmin Sistema', 'superadmin@empresa.com', @pass, 1),
(2, 1, 2, 'Carlos Mendoza (Coordinador TI)', 'carlos.mendoza@empresa.com', @pass, 1),
(3, 2, 2, 'Elena Rostova (Coordinador RRHH)', 'elena.rostova@empresa.com', @pass, 1),
(4, 3, 2, 'Marcos Benítez (Coordinador Mantenimiento)', 'marcos.benitez@empresa.com', @pass, 1),
(5, 1, 3, 'Juan Pérez (Colaborador TI)', 'juan.perez@empresa.com', @pass, 1),
(6, 1, 3, 'Laura Torres (Colaborador TI)', 'laura.torres@empresa.com', @pass, 1),
(7, 2, 3, 'Sofía Ramírez (Colaborador RRHH)', 'sofia.ramirez@empresa.com', @pass, 1),
(8, 1, 3, 'Ana López (Colaborador TI)', 'ana.lopez@empresa.com', @pass, 1),
(9, 3, 3, 'Roberto Gómez (Colaborador Mantenimiento)', 'roberto.gomez@empresa.com', @pass, 1);

-- Vincular Managers a sus Áreas
UPDATE areas SET manager_id = 2 WHERE id = 1;
UPDATE areas SET manager_id = 3 WHERE id = 2;
UPDATE areas SET manager_id = 4 WHERE id = 3;

-- 5. POLÍTICAS DE SLA
INSERT INTO sla_policies (area_id, priority, max_response_hours, max_resolution_hours) VALUES 
(1, 'urgent', 1, 4),
(1, 'high', 2, 8),
(1, 'medium', 4, 24),
(1, 'low', 8, 48),
(2, 'urgent', 2, 6),
(3, 'urgent', 1, 4),
(4, 'urgent', 2, 8);

-- 6. TICKETS DE PRUEBA REALISTAS (Cubriendo todos los estados, prioridades y SLAs)
INSERT INTO tickets (id, code, title, description, priority, status_id, area_id, requester_id, assigned_agent_id, is_sla_breached, created_at) VALUES 
(1, 'TI-2026-001', 'Fallo de Enlace de Red e Impresora IP en Piso 3', 'La impresora departamental en IP 192.168.1.50 perdió conectividad y rechaza los trabajos en cola de impresión de todo el equipo de operaciones.', 'high', 1, 1, 8, NULL, 0, DATE_SUB(NOW(), INTERVAL 3 HOUR)),

(2, 'TI-2026-002', 'Lentitud Intermitente y Timeout en Servidor de Correos', 'Los correos salientes con archivos adjuntos se quedan en cola por más de 20 minutos arrojando error SMTP 504 Gateway Timeout.', 'urgent', 2, 1, 9, 5, 0, DATE_SUB(NOW(), INTERVAL 2 HOUR)),

(3, 'TI-2026-003', 'Aprobación de Licencia y Credenciales VPN para Nuevo Ingreso', 'Se requiere configurar cuenta de VPN Fortinet y acceso a carpetas compartidas del servidor de archivos para el nuevo analista contable.', 'medium', 3, 1, 8, 6, 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),

(4, 'TI-2026-004', 'Restablecimiento de Credenciales de Active Directory', 'Usuario bloqueado tras 3 intentos fallidos de inicio de sesión en estación de trabajo.', 'low', 4, 1, 8, 5, 0, DATE_SUB(NOW(), INTERVAL 2 DAY)),

(5, 'MT-2026-001', 'Fuga de Agua en Climatización Oficina 302', 'Goteo continuo sobre los puestos de trabajo principales. Requiere revisión urgente de bandeja de condensación del aire acondicionado.', 'urgent', 2, 3, 9, 4, 1, DATE_SUB(NOW(), INTERVAL 26 HOUR)),

(6, 'RR-2026-001', 'Emisión de Carta de Trabajo Certificada con Sello Húmedo', 'Solicitud formal para trámite bancario de crédito hipotecario.', 'medium', 1, 2, 5, NULL, 0, DATE_SUB(NOW(), INTERVAL 5 HOUR)),

(7, 'FI-2026-001', 'Error de Sincronización en Cierre de Facturación Profit Plus', 'El módulo de cuentas por cobrar muestra discrepancia de saldos al intentar emitir el informe preliminar mensual.', 'high', 2, 4, 8, NULL, 0, DATE_SUB(NOW(), INTERVAL 4 HOUR));

-- 7. COMENTARIOS Y NOTAS INTERNAS PARA PRUEBAS DE CHAT & FRESHDESK
INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal, created_at) VALUES 
(2, 5, 'Hola Roberto, ya estamos diagnosticando el relay de correo. Identificamos un cuello de botella en el servicio Postfix.', 0, DATE_SUB(NOW(), INTERVAL 90 MINUTE)),
(2, 2, 'Nota de Coordinación TI: Verifiqué los logs del firewall. El tráfico saliente del puerto 587 está siendo saturado por un backup no programado. Ya le ajusté el ancho de banda prioritario.', 1, DATE_SUB(NOW(), INTERVAL 60 MINUTE)),
(2, 9, 'Enterado Juan, quedamos atentos para reanudar el envío de las cotizaciones urgentes.', 0, DATE_SUB(NOW(), INTERVAL 30 MINUTE)),

(3, 6, 'Estimada Ana, he enviado el enlace de verificación al correo corporativo para registrar el segundo factor de autenticación (2FA).', 0, DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(3, 6, 'Nota Técnica: Licencia asignada en el portal de FortiClient. Solo falta que el usuario confirme la instalación del token.', 1, DATE_SUB(NOW(), INTERVAL 4 HOUR)),

(5, 4, 'Se colocó contenedor temporal y se apagó la unidad 2 del piso 3. Técnico de refrigeración en camino con repuestos.', 0, DATE_SUB(NOW(), INTERVAL 20 HOUR)),
(5, 4, 'Nota de Mantenimiento: El compresor requiere reemplazo de manguera de drenaje flexible de 5/8.', 1, DATE_SUB(NOW(), INTERVAL 18 HOUR));
