-- =============================================================================
-- ESQUEMA COMPLETO DE BASE DE DATOS ENTERPRISE SERVICE DESK CON ADJUNTOS
-- Compatible con MySQL 8+ / SQL Server / MariaDB
-- =============================================================================

USE service_desk_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS canned_responses;
DROP TABLE IF EXISTS sla_policies;
DROP TABLE IF EXISTS ticket_audit_logs;
DROP TABLE IF EXISTS ticket_custom_field_values;
DROP TABLE IF EXISTS area_custom_fields;
DROP TABLE IF EXISTS ticket_comments;
DROP TABLE IF EXISTS tickets;
DROP TABLE IF EXISTS kanban_status;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS areas;
DROP TABLE IF EXISTS roles;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. TABLA DE ROLES
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. TABLA DE ÁREAS / DEPARTAMENTOS
CREATE TABLE areas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    manager_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. TABLA DE USUARIOS
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area_id INT NULL,
    role_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE SET NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE areas 
ADD CONSTRAINT fk_area_manager 
FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL;

-- 4. TABLA DE ESTADOS KANBAN
CREATE TABLE kanban_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    sort_order INT NOT NULL DEFAULT 0,
    is_final TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. TABLA DE TICKETS CON ADJUNTOS / CAPTURAS
CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    status_id INT NOT NULL DEFAULT 1,
    area_id INT NOT NULL,
    requester_id INT NOT NULL,
    assigned_agent_id INT NULL,
    attachment_path VARCHAR(255) NULL, -- RUTA DE LA CAPTURA DE PANTALLA O ADJUNTO
    due_date DATETIME NULL,
    is_sla_breached TINYINT(1) DEFAULT 0,
    resolved_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (status_id) REFERENCES kanban_status(id) ON DELETE RESTRICT,
    FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE RESTRICT,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_agent_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. AUDITORÍA E HISTORIAL INMUTABLE
CREATE TABLE ticket_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    old_value VARCHAR(255) NULL,
    new_value VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. POLÍTICAS DE SLA
CREATE TABLE sla_policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area_id INT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL,
    max_response_hours INT NOT NULL DEFAULT 4,
    max_resolution_hours INT NOT NULL DEFAULT 24,
    FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. COMENTARIOS Y NOTAS PRIVADAS
CREATE TABLE ticket_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    is_internal TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
