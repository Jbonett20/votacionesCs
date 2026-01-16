# Sistema de Votaciones - Gestión Electoral

Sistema completo de gestión de votaciones desarrollado con PHP, JavaScript, jQuery, Bootstrap 5 y MeekroDB.

## 🚀 Características

- ✅ **Sistema de Login Seguro** con autenticación y gestión de sesiones
- ✅ **Gestión de Roles**: SuperAdministrador, Administrador, Líder, Votante
- ✅ **Dashboard Interactivo** con estadísticas en tiempo real
- ✅ **Gestión de Usuarios** (CRUD completo)
- ✅ **Gestión de Líderes** (CRUD completo)
- ✅ **Gestión de Votantes** con asignación de líderes
- ✅ **DataTables** con búsqueda, paginación y ordenamiento
- ✅ **Diseño Responsive** compatible con todos los dispositivos
- ✅ **Interfaz Moderna** con animaciones y efectos

## 📋 Requisitos

- PHP 7.4 o superior
- MySQL 5.7 o superior
- Apache (XAMPP, WAMP, LAMP, etc.)
- Navegador web moderno

## 🛠️ Instalación

### 1. Configurar la Base de Datos

1. Inicia tu servidor MySQL (XAMPP, WAMP, etc.)
2. Abre phpMyAdmin o tu cliente MySQL favorito
3. Ejecuta el script de creación de base de datos (el que compartiste)
4. Ejecuta el script `usuario_inicial.sql` para crear el usuario administrador:

```sql
mysql -u root -p < usuario_inicial.sql
```

O copia y pega el contenido en phpMyAdmin.

### 2. Configurar la Conexión

Abre el archivo `admin/config/db.php` y ajusta los parámetros de conexión:

```php
DB::$host = 'localhost';
DB::$user = 'root';
DB::$password = ''; // Tu contraseña de MySQL
DB::$dbName = 'bd_votaciones';
```

### 3. Iniciar el Servidor

Si usas XAMPP:
- Coloca el proyecto en `C:\xampp\htdocs\eleccionCS\`
- Inicia Apache desde el panel de XAMPP
- Accede a: `http://localhost/eleccionCS/`

## 🔐 Credenciales Iniciales

**SuperAdministrador:**
- Usuario: `admin`
- Contraseña: `admin123`

**Administrador de Prueba:**
- Usuario: `admin2`
- Contraseña: `admin123`

**Líder de Prueba:**
- Usuario: `lider1`
- Contraseña: `lider123`

> ⚠️ **IMPORTANTE**: Cambia estas contraseñas después del primer inicio de sesión.

## 👥 Roles y Permisos

### SuperAdministrador (Rol 1)
- Acceso total al sistema
- Crear/editar/eliminar Administradores
- Crear/editar/eliminar Líderes
- Crear/editar/eliminar Votantes
- Ver todos los reportes

### Administrador (Rol 2)
- Crear/editar/eliminar Líderes
- Crear/editar/eliminar Votantes
- Asignar votantes a líderes o a sí mismo
- Ver reportes de su gestión

### Líder (Rol 3)
- Crear/editar/eliminar sus propios Votantes
- Ver solo sus votantes registrados
- Ver reportes de sus votantes

### Votante (Rol 4)
- Solo datos en la base de datos
- No tiene acceso al sistema

## 📱 Estructura del Proyecto

```
eleccionCS/
│
├── index.php                  # Página de login
├── usuario_inicial.sql        # Script SQL inicial
├── README.md                  # Este archivo
│
├── vendor/
│   └── meekrodb/
│       └── db.class.php       # Clase MeekroDB
│
└── admin/
    ├── config/
    │   ├── db.php             # Configuración de BD
    │   └── session.php        # Gestión de sesiones
    │
    ├── controllers/
    │   ├── login_controller.php
    │   ├── logout_controller.php
    │   ├── lideres_controller.php
    │   └── votantes_controller.php
    │
    ├── views/
    │   ├── dashboard.php      # Panel principal
    │   ├── lideres.php        # Gestión de líderes
    │   ├── votantes.php       # Gestión de votantes
    │   └── partials/
    │       ├── sidebar.php    # Menú lateral
    │       └── topbar.php     # Barra superior
    │
    ├── assets/
    │   ├── css/
    │   │   ├── login.css
    │   │   ├── dashboard.css
    │   │   └── tables.css
    │   └── js/
    │       ├── login.js
    │       ├── dashboard.js
    │       ├── lideres.js
    │       └── votantes.js
    │
    └── models/
        └── (modelos si son necesarios)
```

## 🎨 Tecnologías Utilizadas

- **Backend**: PHP 7.4+
- **Base de Datos**: MySQL con MeekroDB
- **Frontend**: HTML5, CSS3, JavaScript
- **Frameworks**: 
  - Bootstrap 5.3.2
  - jQuery 3.7.1
  - DataTables 1.13.7
  - Select2 4.1.0
  - SweetAlert2 11
  - Font Awesome 6.5.1
- **Tipografía**: Google Fonts (Poppins)

## 📝 Funcionalidades Principales

### Sistema de Login
- Autenticación segura con password_hash
- Validación de estado de usuario
- Opción "Recordarme"
- Mensajes de error personalizados

### Dashboard
- Estadísticas según rol del usuario
- Actividad reciente
- Diseño adaptable

### Gestión de Líderes (Admin)
- Crear líderes con formulario completo
- Editar información de líderes
- Cambiar estado (Activo/Inactivo)
- Tabla con DataTables

### Gestión de Votantes
- Crear votantes asociados a un líder
- Los administradores pueden elegir líder o registrarse como líder
- Los líderes automáticamente se asignan como líderes de sus votantes
- Editar y eliminar votantes (con permisos)
- Tabla interactiva con búsqueda y filtros

## 🔧 Personalización

### Cambiar Colores
Edita las variables CSS en `admin/assets/css/dashboard.css`:

```css
:root {
    --primary-color: #667eea;
    --secondary-color: #764ba2;
    --success-color: #10b981;
    /* ... */
}
```

### Agregar Nuevos Módulos
1. Crea la vista en `admin/views/`
2. Crea el controlador en `admin/controllers/`
3. Crea el JavaScript en `admin/assets/js/`
4. Agrega el menú en `admin/views/partials/sidebar.php`

## 🐛 Solución de Problemas

### Error de Conexión a la Base de Datos
- Verifica que MySQL esté corriendo
- Revisa las credenciales en `admin/config/db.php`
- Asegúrate de que la base de datos existe

### Errores de Permisos
- Verifica que la carpeta tenga permisos de lectura/escritura
- En Linux/Mac: `chmod -R 755 eleccionCS/`

### Páginas en Blanco
- Activa el modo debug en `admin/config/db.php`:
  ```php
  define('DEBUG_MODE', true);
  ```
- Revisa los logs de Apache/PHP

## 📄 Licencia

Este proyecto es de código abierto y está disponible bajo la Licencia MIT.

## 👨‍💻 Soporte

Para soporte o preguntas:
- Revisa la documentación en este README
- Verifica los comentarios en el código
- Consulta la consola del navegador para errores JavaScript

## 🎯 Próximas Características

- [ ] Módulo de Reportes avanzados
- [ ] Exportación a Excel/PDF
- [ ] Gráficos estadísticos
- [ ] Sistema de notificaciones
- [ ] Historial de cambios
- [ ] Recuperación de contraseña
- [ ] Autenticación de dos factores

---

**Desarrollado con ❤️ para gestión electoral eficiente**
