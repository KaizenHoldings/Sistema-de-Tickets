# Sistema de Gestión de Tickets & Service Desk

Sistema web completo de mesa de ayuda y gestión de tickets desarrollado en PHP y MySQL con interfaz moderna (Kanban, métricas y panel de administración).

## 🚀 Características Principales

- **Gestión de Tickets:** Creación, edición, cambio de estado en tiempo real y vista en tablero Kanban.
- **Integración con SharePoint:** Sincronización automática y bajo demanda con la lista corporativa de SharePoint `Solicitudes - SSGG e IT` vía Microsoft Graph API (solo lectura `GET`).
- **Roles y Permisos:**
  - **Administrador:** Control total del sistema, gestión de usuarios, roles, métricas globales.
  - **Técnico:** Asignación de tickets, resolución, comentarios técnicos y actualización de avance.
  - **Usuario:** Creación y seguimiento de sus propias solicitudes.
- **Historial y Comentarios:** Hilo de seguimiento por ticket con soporte para notas internas y públicas.
- **Dashboard de Métricas:** KPIs de tickets resueltos, pendientes, distribución por prioridad y tiempos de respuesta.
- **Diseño Moderno:** Interfaz responsiva inspirada en interfaces limpias y dinámicas.

---

## 🛠️ Requisitos Previos

- **Opción A (Recomendada):** [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Docker Compose v2+).
- **Opción B:** **XAMPP** (Apache, PHP 8.0+ y MySQL/MariaDB).
- Navegador web moderno (Chrome, Edge, Firefox).

---

## 🐳 Puesta en Marcha con Docker Compose (Rápido)

1. **Iniciar los Contenedores:**
   Ejecuta en la raíz del proyecto:
   ```bash
   docker compose up -d --build
   ```

2. **Acceder a los Servicios:**
   - **Aplicación Web:** [http://localhost:8080](http://localhost:8080)
   - **phpMyAdmin:** [http://localhost:8081](http://localhost:8081) (Usuario: `root`, Contraseña: `root`)

> Las tablas y semillas de prueba (`sql/schema.sql` y `sql/seeds.sql`) se importan automáticamente al crear el contenedor de MySQL.

---

## 📦 Puesta en Marcha Tradicional (XAMPP)

1. **Ubicación del Proyecto:**
   Colocar la carpeta del proyecto dentro de la carpeta `htdocs` de XAMPP:
   ```
   C:\xampp\htdocs\sistema-tickets
   ```

2. **Iniciar Servicios:**
   Abrir el **Panel de Control de XAMPP** y encender los módulos de **Apache** y **MySQL**.

3. **Configuración de Base de Datos:**
   - Entrar a [phpMyAdmin](http://localhost/phpmyadmin/).
   - Crear una base de datos llamada `service_desk_db`.
   - Importar en orden los archivos ubicados en la carpeta `sql/`:
     1. `sql/schema.sql` (Estructura de tablas)
     2. `sql/seeds.sql` (Datos iniciales y usuarios de prueba)

4. **Acceso al Sistema:**
   Abrir el navegador e ingresar a:
   ```
   http://localhost/sistema-tickets/public/
   ```

---

## 👥 Credenciales de Prueba

| Rol | Usuario / Email | Contraseña |
| :--- | :--- | :--- |
| **Administrador** | `admin@empresa.com` | `admin123` |
| **Técnico** | `tecnico@empresa.com` | `admin123` |
| **Usuario** | `usuario@empresa.com` | `admin123` |

---

## 📂 Estructura del Proyecto

```
sistema-tickets/
├── config/             # Configuración de base de datos PDO
├── public/             # Punto de entrada público (index.php, login.php, api.php, css, js)
├── sql/                # Scripts SQL (schema y semillas de prueba)
├── src/                # Lógica de negocio (Controladores, Middleware, Servicios)
├── .gitignore          # Exclusión de temporales
└── README.md           # Documentación del proyecto
```
