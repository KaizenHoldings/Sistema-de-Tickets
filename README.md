# Sistema de Gestión de Tickets & Service Desk

Sistema web completo de mesa de ayuda y gestión de tickets desarrollado en PHP y MySQL con interfaz moderna (Kanban, métricas y panel de administración).

## 🚀 Características Principales

- **Gestión de Tickets:** Creación, edición, cambio de estado en tiempo real y vista en tablero Kanban.
- **Roles y Permisos:**
  - **Administrador:** Control total del sistema, gestión de usuarios, roles, métricas globales.
  - **Técnico:** Asignación de tickets, resolución, comentarios técnicos y actualización de avance.
  - **Usuario:** Creación y seguimiento de sus propias solicitudes.
- **Historial y Comentarios:** Hilo de seguimiento por ticket con soporte para notas internas y públicas.
- **Dashboard de Métricas:** KPIs de tickets resueltos, pendientes, distribución por prioridad y tiempos de respuesta.
- **Diseño Moderno:** Interfaz responsiva inspirada en interfaces limpias y dinámicas.

---

## 🛠️ Requisitos Previos

- **XAMPP** (o cualquier entorno con Apache y PHP 8.0+ y MySQL/MariaDB).
- Navegador web moderno (Chrome, Edge, Firefox).

---

## 📦 Instalación y Puesta en Marcha

1. **Ubicación del Proyecto:**
   Colocar la carpeta del proyecto dentro de la carpeta `htdocs` de XAMPP:
   ```
   C:\xampp\htdocs\sistema-tickets
   ```

2. **Iniciar Servicios:**
   Abrir el **Panel de Control de XAMPP** y encender los módulos de **Apache** y **MySQL**.

3. **Configuración de Base de Datos:**
   - Entrar a [phpMyAdmin](http://localhost/phpmyadmin/).
   - Crear una base de datos llamada `service_desk_db` (o el nombre que prefieras).
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
