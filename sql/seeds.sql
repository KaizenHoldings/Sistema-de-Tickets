-- =============================================================================
-- SEEDS CON REGISTROS REALES DE SHAREPOINT ('Solicitudes - SSGG e IT')
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

-- 3. DEPARTAMENTOS / ÁREAS (Mapeadas con SharePoint IT y SSGG)
INSERT INTO areas (id, name, description, manager_id) VALUES 
(1, 'Tecnología e Informática (TI)', 'Soporte técnico, redes, infraestructura, servidores y telecomunicaciones.', NULL),
(2, 'Recursos Humanos (RRHH)', 'Gestión del talento humano, nómina, permisos y certificaciones.', NULL),
(3, 'Mantenimiento y Servicios Generales (SSGG)', 'Infraestructura física, climatización, fontanería, electricidad y mantenimiento.', NULL),
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

-- 6. REGISTROS REALES DE TICKETS DE SHAREPOINT (Solicitudes - SSGG e IT)
INSERT INTO tickets (id, sharepoint_id, code, title, description, priority, status_id, area_id, requester_id, assigned_agent_id, is_sla_breached, created_at) VALUES 
(1, 1, 'SG-SP-00001', 'NIMBOX', 'Evaluar posibilidad de hacer una tabla de relaciones entre cliente y varios vendedores | Empresa: Inveca | Responsable SharePoint: MG', 'medium', 4, 3, 8, 2, 0, '2024-05-31 09:00:00'),
(2, 2, 'SG-SP-00002', 'Cableado  Wifi/red', 'Revisión con los distintos proveedores para presentar presupuesto. En espera de respuesta de la dirección | Empresa: Inveca | Responsable SharePoint: JD-CS', 'low', 4, 3, 8, 5, 0, '2024-05-31 09:00:00'),
(3, 3, 'SG-SP-00003', 'Instalación de Aires acondicionados', 'Evaluar proveedores de aires e instalacion en las tres oficinas Mariche. Pendiente por mudanza | Empresa: Inveca | Responsable SharePoint: MG-JD', 'high', 4, 3, 8, 2, 0, '2024-05-31 09:00:00'),
(4, 4, 'SG-SP-00004', 'Asignación de Equipos', 'Lisseth Altuve-Hecyelin | Empresa: Inveca | Responsable SharePoint: MG', 'low', 3, 3, 8, 2, 0, '2024-05-31 09:00:00'),
(5, 5, 'SG-SP-00005', 'Facturación Vigía', 'Levantamiento de información para la solicitud de activación del proceso en Profit al consultor | Empresa: Inveca | Responsable SharePoint: MG', 'medium', 2, 3, 8, 2, 0, '2023-12-13 09:00:00'),
(6, 6, 'SG-SP-00006', 'Revisión de Equipo de Bepa', 'Wifi desabilitado | Empresa: Inveca | Responsable SharePoint: MG', 'urgent', 4, 3, 8, 2, 0, '2024-06-06 09:00:00'),
(7, 7, 'SG-SP-00007', 'Revision de equipos de conexion del vigia', 'Equipo se encontraba inabilitado para conexion | Empresa: Inveca | Responsable SharePoint: MG', 'medium', 1, 3, 8, 2, 0, '2024-06-06 09:00:00'),
(8, 8, 'SG-SP-00008', 'Revisión de equipo de conexion remota', 'Se debe reinstalar windows | Empresa: Inveca | Responsable SharePoint: MG-JD', 'low', 3, 3, 8, 2, 0, '2024-06-07 09:00:00'),
(9, 9, 'SG-SP-00009', 'Revisión de cuenta de correo nvillamizar', 'Se evidencia normalidad, se indica que  llame directo para hacer revisión | Empresa: Inveca | Responsable SharePoint: MG', 'high', 4, 3, 8, 2, 0, '2024-06-07 09:00:00'),
(10, 10, 'SG-SP-00010', 'Servicio:  Gestión de servicio IT', 'Revision de impresora-CXP | Empresa: Inveca | Responsable SharePoint: JD', 'low', 2, 3, 8, 5, 0, '2024-06-10 09:00:00'),
(11, 11, 'SG-SP-00011', 'Servicio: Traslados a personal', 'Traslado areopuerto-Rolando-Edgar- IDA | Empresa: Inveca | Responsable SharePoint: JD', 'medium', 4, 3, 8, 5, 0, '2024-06-10 09:00:00'),
(12, 12, 'SG-SP-00012', 'Servicio: Recepción de solicitud y registro entrega de materiales de oficina', 'Envío de materiles Cendis-Mariche | Empresa: Inveca | Responsable SharePoint: JD', 'urgent', 3, 3, 8, 5, 0, '2024-06-10 09:00:00'),
(13, 13, 'SG-SP-00013', 'Servicio: Gestión koupons recepción de productos Socado', 'Recepción de mercancía en zona de carga de la torre P&G/traslado hasta piso 4 | Empresa: Inveca | Responsable SharePoint: OJ-JD', 'medium', 4, 3, 8, 5, 0, '2024-06-05 09:00:00'),
(14, 14, 'SG-SP-00014', 'Servicio: Gestión koupons Recepción de productos INVECA', 'Recepción de mercancía en zona de carga de la torre P&G/traslado hasta piso 4 | Empresa: Inveca | Responsable SharePoint: OJ-JD', 'low', 1, 3, 8, 5, 0, '2024-06-06 09:00:00'),
(15, 15, 'SG-SP-00015', 'Servicio: Gestión koupons', 'Armado/Entrega de beneficio en oficina | Empresa: Inveca | Responsable SharePoint: OJ-JD', 'high', 2, 3, 8, 5, 0, '2024-06-07 09:00:00'),
(16, 16, 'SG-SP-00016', 'Revisipon equipo Laptop', 'Bepa su equipo no queria iniciar | Empresa: Inveca | Responsable SharePoint: MG', 'low', 3, 3, 8, 2, 0, '2024-06-10 09:00:00'),
(17, 17, 'SG-SP-00017', 'Gestión de información vehiculos', 'Compra/en espera de respuesta | Empresa: Inveca | Responsable SharePoint: MG', 'medium', 4, 3, 8, 2, 0, '2024-06-01 09:00:00'),
(18, 18, 'SG-SP-00018', 'Reconexion de profit', 'Equipo LA reconexión profit | Empresa: Inveca | Responsable SharePoint: MG', 'urgent', 4, 3, 8, 2, 0, '2024-06-11 09:00:00'),
(19, 19, 'SG-SP-00019', 'Servicio: Traslados a personal', 'Reporte de error en viajes del corporativo WAWA | Empresa: Inveca | Responsable SharePoint: JD', 'medium', 4, 3, 8, 5, 0, '2024-06-11 09:00:00'),
(20, 20, 'SG-SP-00020', 'Servicio:  Gestión de servicio IT', 'Reporte de caída internet almacén Mariche-solucionado | Empresa: Inveca | Responsable SharePoint: JD', 'low', 2, 3, 8, 5, 0, '2024-06-11 09:00:00'),
(21, 21, 'SG-SP-00021', 'Servicio: Traslados corporativo Ridery', 'Programación semanal de traslado nocturno almacén Mariche | Empresa: Inveca | Responsable SharePoint: JD', 'high', 1, 3, 8, 5, 0, '2024-06-11 09:00:00'),
(22, 22, 'SG-SP-00022', 'Servicio:  Gestión de servicio IT', 'Se creo usuario windows, intalación de AnyDesk para conecxion remota de Planta el Vigía | Empresa: Inveca | Responsable SharePoint: CS', 'low', 4, 3, 8, 6, 0, '2024-06-10 09:00:00'),
(23, 23, 'SG-SP-00023', 'Servicio:  Traslado', 'Traslado de sobre para Guadalupe Contreras | Empresa: Inveca | Responsable SharePoint: CS', 'medium', 4, 3, 8, 6, 0, '2024-06-11 09:00:00'),
(24, 24, 'SG-SP-00024', 'Servicio: Gestión de inventarios-Almacén', 'Recepción de café para el inventario de inveca | Empresa: Inveca | Responsable SharePoint: OJ', 'urgent', 3, 3, 8, NULL, 0, '2024-06-12 09:00:00'),
(25, 25, 'SG-SP-00025', 'Servicio: Gestión de inventarios-Almacén', 'Actualización de inventario de productos sacados de almacén | Empresa: Inveca | Responsable SharePoint: OJ', 'medium', 2, 3, 8, NULL, 0, '2024-06-12 09:00:00'),
(26, 26, 'SG-SP-00026', 'Servicio: Gestión de inventarios-Almacén', 'Entrega de productos de limpieza y cocina | Empresa: Inveca | Responsable SharePoint: OJ', 'low', 4, 3, 8, NULL, 0, '2024-06-12 09:00:00'),
(27, 27, 'SG-SP-00027', 'Servicio:  Gestión de servicio IT', 'Reseteo de clave de correo | Empresa: Inveca | Responsable SharePoint: MG', 'high', 4, 3, 8, 2, 0, '2024-06-12 09:00:00'),
(28, 28, 'SG-SP-00028', 'Servicio: Gestión mantenimiento en oficina', 'Instalación de puerta vidrio Sala reunión/
 Instalación de papel esmerilado | Empresa: Inveca | Responsable SharePoint: JD', 'low', 1, 3, 8, 5, 0, '2024-06-12 09:00:00'),
(29, 29, 'SG-SP-00029', 'Servicio:  Gestión de servicio IT', 'Actualización de clave de correo para ser utilizado en telefono personal de Manuel Duque | Empresa: Inveca | Responsable SharePoint: CS', 'medium', 4, 3, 8, 6, 0, '2024-06-12 09:00:00'),
(30, 30, 'SG-SP-00030', 'Servicio: Traslados a personal', 'Trasado al Seniat área legal | Empresa: Inveca | Responsable SharePoint: JD', 'urgent', 2, 3, 8, 5, 0, '2024-06-13 09:00:00'),
(31, 31, 'SG-SP-00031', 'Servicio:  Gestión de servicio IT', 'Carga de vendedores adicionales por cliente para Nimbox/la respuesta de consultor Profit se logro tarde | Empresa: Inveca | Responsable SharePoint: MG', 'medium', 4, 3, 8, 2, 0, '2024-06-03 09:00:00'),
(32, 32, 'SG-SP-00032', 'Servicio: Recepción de mercancía en zona de carga de la torre P&G.', 'Recepción de mercancía -comedor-limpieza-oficina desde la zona de carga hasta
 oficina P&G- POVIC | Empresa: Inveca | Responsable SharePoint: JD', 'low', 3, 3, 8, 5, 0, '2024-06-14 09:00:00'),
(33, 33, 'SG-SP-00033', 'Servicio:  Traslado', 'Envío de sobre del area Legal a Los Simbolos | Empresa: Inveca | Responsable SharePoint: CS', 'high', 4, 3, 8, 6, 0, '2024-06-14 09:00:00'),
(34, 34, 'SG-SP-00034', 'Entrega de equipo para Lisseth Altuve', 'Preparación de equipo, instalación de programas y entrega | Empresa: Inveca | Responsable SharePoint: MG', 'low', 4, 3, 8, 2, 0, '2024-06-10 09:00:00'),
(35, 35, 'SG-SP-00035', 'Servicio:  Gestión de servicio IT', 'Restablecimiento de error para conectar a microsoft 365 de 3 equipos | Empresa: Inveca | Responsable SharePoint: MG', 'medium', 1, 3, 8, 2, 0, '2024-06-14 09:00:00'),
(36, 36, 'SG-SP-00036', 'Servicio:  Gestión de servicio IT', 'Soporte para reinstalar el correo y autenticador en nuevo teléfono Luis Peña | Empresa: Inveca | Responsable SharePoint: MG', 'urgent', 3, 3, 8, 2, 0, '2024-06-17 09:00:00'),
(37, 37, 'SG-SP-00037', 'Servicio: Gestión de inventarios-Almacén', 'Recibí resguarde en el almacén mercancia de materiales/lim/com/ofi junio | Empresa: Inveca | Responsable SharePoint: OJ', 'medium', 4, 3, 8, NULL, 0, '2024-06-17 09:00:00'),
(38, 38, 'SG-SP-00038', 'Servicio: Gestión mantenimiento en oficina', 'Supervisión de instalación de Cartelera Fiscal en Almacén de Mariche | Empresa: Inveca | Responsable SharePoint: CS', 'low', 4, 3, 8, 6, 0, '2024-06-14 09:00:00'),
(39, 39, 'SG-SP-00039', 'Servicio: Gestión mantenimiento en oficina', 'Se atendio a proveedor Reinaldo para realizar presupuestos por arreglos en almacén de Mariche | Empresa: Inveca | Responsable SharePoint: CS', 'high', 4, 3, 8, 6, 0, '2024-06-13 09:00:00'),
(40, 40, 'SG-SP-00040', 'Servicio: Gestión mantenimiento en oficina', 'Se atendio a proveedor Jonatan para realizar presupuestos por arreglos en almacén de Mariche | Empresa: Inveca | Responsable SharePoint: CS', 'low', 2, 3, 8, 6, 0, '2024-06-13 09:00:00'),
(41, 41, 'SG-SP-00041', 'Gestión de On-Boarding', 'Entrega de 2 kit uniformes para nuevos ingresos | Empresa: Inveca | Responsable SharePoint: OJ', 'medium', 4, 3, 8, NULL, 0, '2024-06-17 09:00:00'),
(42, 42, 'SG-SP-00042', 'Gestión de On-Boarding', 'Entregada libreta de inveca nuevo ingreso | Empresa: Inveca | Responsable SharePoint: OJ', 'urgent', 1, 3, 8, NULL, 0, '2024-06-13 09:00:00'),
(43, 43, 'SG-SP-00043', 'Servicio: Gestión de inventarios-Almacén', 'Preparación y entrega de materiales de lim/ofi/coc | Empresa: Inveca | Responsable SharePoint: OJ', 'medium', 4, 3, 8, NULL, 0, '2024-06-17 09:00:00'),
(44, 44, 'SG-SP-00044', 'Servicio:  Gestión de servicio IT', 'Asignación de quipo a Yessica Gelvez, para ser utilizado como conecxión remota | Empresa: Inveca | Responsable SharePoint: CS', 'low', 3, 3, 8, 6, 0, '2024-06-12 09:00:00'),
(45, 45, 'SG-SP-00045', 'Servicio:  Gestión de servicio IT', 'Creación correos nuevos ingresos y configuración en telefono de los ingresos | Empresa: Inveca | Responsable SharePoint: MG', 'high', 2, 3, 8, 2, 0, '2024-06-14 09:00:00'),
(46, 46, 'SG-SP-00046', 'Servicio:  Gestión de servicio IT', 'Atención de soporte via remota lapto Jose Zambrano conflicto por archivo en OneDrive | Empresa: Inveca | Responsable SharePoint: MG', 'low', 4, 3, 8, 2, 0, '2024-06-17 09:00:00'),
(47, 47, 'SG-SP-00047', 'Servicio:  Gestión de servicio IT', 'Reunión NImbox para determinar nuevo proceso con pedido y almacen | Empresa: Inveca | Responsable SharePoint: MG', 'medium', 4, 3, 8, 2, 0, '2024-06-12 09:00:00'),
(48, 48, 'SG-SP-00048', 'Solicitud de pagos por servicios para proveedores:', 'Recepción/revisión/registro de facturas.
Gestionar el pago con CxP-crear OC y NC Profit | Empresa: Inveca | Responsable SharePoint: JD', 'urgent', 3, 3, 8, 5, 0, '2024-06-17 09:00:00'),
(49, 49, 'SG-SP-00049', 'Servicio: Gestión de expedientes', 'Recepción y resguardo de 2 expedientes de nuevos ingresos | Empresa: Inveca | Responsable SharePoint: OJ', 'medium', 1, 3, 8, NULL, 0, '2024-06-17 09:00:00'),
(50, 50, 'SG-SP-00050', 'Gestión de On-Boarding', 'Entrega de 2 kit uniformes para nuevos ingresos | Empresa: Inveca | Responsable SharePoint: OJ', 'low', 2, 3, 8, NULL, 0, '2024-06-17 09:00:00'),
(51, 51, 'SG-SP-00051', 'Servicio: Gestión de inventarios-Almacén', 'Entrega de meterial de oficina para expedientes de importación | Empresa: Inveca | Responsable SharePoint: OJ', 'high', 4, 3, 8, NULL, 0, '2024-06-18 09:00:00'),
(52, 52, 'SG-SP-00052', 'Servicio:  Entrega de activos fijos para el personal.', 'Entrega de 2 sillas para cuentas por cobrar | Empresa: Inveca | Responsable SharePoint: OJ', 'low', 3, 3, 8, NULL, 0, '2024-06-17 09:00:00'),
(53, 53, 'SG-SP-00053', 'Servicio: Gestión de inventarios-Almacén', 'Entrega de productos de limpieza , cocina y oficina | Empresa: Inveca | Responsable SharePoint: OJ', 'medium', 4, 3, 8, NULL, 0, '2024-06-19 09:00:00'),
(54, 54, 'SG-SP-00054', 'Servicio: Gestión mantenimiento', 'Recepcción de tapiceros-revisión de muebles oficina de SR Rolando | Empresa: Inveca | Responsable SharePoint: JD', 'urgent', 4, 3, 8, 5, 0, '2024-06-19 09:00:00'),
(55, 55, 'SG-SP-00055', 'Servicio:  Traslado', 'Envío de carpeta a Bomberos de Caricuao por parte de Legal | Empresa: Inveca | Responsable SharePoint: CS', 'medium', 2, 3, 8, 6, 0, '2024-06-18 09:00:00'),
(56, 56, 'SG-SP-00056', 'Gestión de On-Boarding', 'Ruta kaizen-Coordinación de transporte tercerizado
 para la actividad 25/06 | Empresa: Inveca | Responsable SharePoint: JD', 'low', 1, 3, 8, 5, 0, '2024-06-18 09:00:00'),
(57, 57, 'SG-SP-00057', 'Servicio: Estacionamiento P&G-Pago Directo', 'Habilitar la salida por ticket de pago directo  usuarios INVECA | Empresa: Inveca | Responsable SharePoint: JD', 'high', 4, 3, 8, 5, 0, '2024-06-17 09:00:00'),
(58, 58, 'SG-SP-00058', 'Solicitud de pagos por servicios para proveedores:', 'Recepción/revisión/registro de facturas.
Gestionar el pago con CxP/envió de comprobantes de pago y retenciones | Empresa: Inveca | Responsable SharePoint: JD', 'low', 4, 3, 8, 5, 0, '2024-06-17 09:00:00'),
(59, 59, 'SG-SP-00059', 'Gestión de On-Boarding', 'Ruta kaizen-Coordinación de transporte tercerizado 
para la actividad 25/06 | Empresa: Inveca | Responsable SharePoint: JD', 'medium', 4, 3, 8, 5, 0, '2024-06-17 09:00:00'),
(60, 60, 'SG-SP-00060', 'Servicio:  Gestión de servicio IT', 'Cambio de RJ-45 a cable utilizado por Hecyelin Castro | Empresa: Inveca | Responsable SharePoint: SG', 'urgent', 2, 3, 8, NULL, 0, '2024-06-21 09:00:00');

-- 7. COMENTARIOS ASOCIADOS A LOS TICKETS REALES
INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal, created_at) VALUES 
(1, 2, 'Evaluando requerimiento de relaciones cliente/vendedor en NIMBOX.', 0, DATE_SUB(NOW(), INTERVAL 90 MINUTE)),
(2, 5, 'En espera de aprobación presupuestaria para el cableado y puntos de red.', 0, DATE_SUB(NOW(), INTERVAL 45 MINUTE)),
(3, 2, 'Cotizaciones recibidas para climatización. Coordinando fecha de mudanza para instalación.', 1, DATE_SUB(NOW(), INTERVAL 30 MINUTE));