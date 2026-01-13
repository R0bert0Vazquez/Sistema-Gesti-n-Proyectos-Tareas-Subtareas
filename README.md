# Sistema-Gestion-Proyectos-Tareas-Subtareas

# Sistema de Gestión de Proyectos y Tareas (API REST)

![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Architecture](https://img.shields.io/badge/Architecture-MVC-orange?style=flat-square)

Backend desarrollado en **PHP Nativo** (Vanilla) implementando una arquitectura **MVC** propia. Este proyecto es el resultado de una prueba técnica para demostrar el dominio del lenguaje, lógica de programación y diseño de bases de datos relacionales sin la abstracción de frameworks.

## 📋 Descripción del Proyecto

El sistema es una API RESTful diseñada para gestionar el flujo de trabajo de proyectos. Permite a los usuarios registrarse, crear proyectos, asignar tareas y subtareas, y realizar comentarios en cualquiera de estos niveles.

El núcleo del sistema cuenta con un **enrutador personalizado** y un manejo de respuestas JSON centralizado, asegurando que todas las salidas de la API sean consistentes.

### Características Técnicas

* **PHP Puro:** Sin uso de Laravel, Symfony u otros frameworks. Todo el core (Rutas, Controladores, Modelos) fue construido desde cero.
* **Seguridad:** Uso de sentencias preparadas con **PDO** para evitar inyecciones SQL.
* **Autenticación:** Sistema de Login con hash de contraseñas (`password_hash`) y autenticación mediante Token (Bearer).
* **Polimorfismo:** El módulo de **Comentarios** reutiliza una única tabla para asociarse dinámicamente a Proyectos, Tareas o Subtareas.
* **Validación de Propiedad:** Middleware lógico que impide que un usuario manipule recursos (editar/borrar) que no le pertenecen.

## ⚙️ Requisitos del Entorno

Para desplegar este proyecto localmente necesitas:

* **PHP 8.0** o superior.
* **MySQL** / MariaDB.
* **Apache** con el módulo `mod_rewrite` habilitado (Esencial para que funcione el archivo `.htaccess` y las rutas amigables).
* **Postman** o Insomnia para probar los endpoints.

## 🚀 Instalación y Configuración

1.  **Clonar el repositorio:**
    ```bash
    git clone [https://github.com/tu-usuario/Sistema-Gestion-Tareas.git](https://github.com/tu-usuario/Sistema-Gestion-Tareas.git)
    cd Sistema-Gestion-Tareas
    ```

2.  **Base de Datos:**
    * Crea una base de datos vacía en tu gestor (ej. `gestion_tareas_db`).
    * Importa el script SQL ubicado en la carpeta `/database` (o raíz) del proyecto.

3.  **Configuración:**
    * Ve al archivo `config/conexionBD.php`.
    * Actualiza las credenciales de conexión:
    ```php
    define('HOST', 'localhost');
    define('DB', 'nombre_de_tu_bd');
    define('USER', 'root');
    define('PASSWORD', '');
    ```

4.  **Ejecución:**
    Asegúrate de que el proyecto esté dentro de `htdocs` (si usas XAMPP) o configurado en tu VirtualHost.
    * Ruta base típica: `http://localhost/SistemaGestionTareas/public/`

## 🔌 Documentación de la API

Los endpoints responden en formato JSON. Se requiere el encabezado `Authorization: Bearer {TOKEN}` para todas las rutas excepto registro y login.

### 👤 Usuarios (Auth)

| Método | Endpoint | Descripción |
| :--- | :--- | :--- |
| `POST` | `/usuario/registro` | Crear una nueva cuenta |
| `POST` | `/usuario/login` | Iniciar sesión y obtener Token |

### 📂 Proyectos y Tareas

| Método | Endpoint | Descripción | Body (JSON) Requerido |
| :--- | :--- | :--- | :--- |
| `GET` | `/tarea` | Listar todas mis tareas | N/A |
| `GET` | `/tarea?id_proyecto=1` | Listar tareas de un proyecto | N/A |
| `POST` | `/tarea` | Crear tarea | `{"nombre": "...", "id_proyecto": 1}` |
| `PUT` | `/tarea/{id}` | Actualizar tarea | `{"estado": "Completado"}` |
| `POST` | `/subtarea` | Crear subtarea | `{"nombre": "...", "id_tarea": 1}` |

### 💬 Comentarios (Polimórficos)

Para comentar se debe especificar el tipo de elemento (`proyecto`, `tarea`, `subtarea`).

| Método | Endpoint | Ejemplo de Uso |
| :--- | :--- | :--- |
| `GET` | `/comentario` | `?tipo=tarea&id=5` |
| `POST` | `/comentario` | Body: `{"contenido": "...", "elemento_id": 5, "elemento_tipo": "tarea"}` |

---

## 📂 Estructura de Carpetas

```text
/
├── config/          # Configuración de BD
├── controllers/     # Lógica de los endpoints (Usuario, Tarea, etc.)
├── public/          # Entry Point (index.php) y manejo de rutas
├── utils/           # Clases auxiliares (Manejo de Vistas JSON, Excepciones)
└── .htaccess        # Redireccionamiento de tráfico a public/

