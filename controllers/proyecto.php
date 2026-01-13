<?php
require_once "../config/conexionBD.php";
require_once "usuario.php"; // Necesario para usar Usuario::autorizar()

class Proyecto
{
    // Constantes de la tabla "proyectos"
    const NOMBRE_TABLA = "proyectos";
    const ID = "id";
    const NOMBRE = "nombre";
    const DESCRIPCION = "descripcion";
    const ESTADO_PROYECTO = "estado"; // 'estado' se usa en constantes de API, renombramos para evitar colisión
    const ID_USUARIO = "id_usuario";
    const CREATED_AT = "created_at";

    // Constantes de estado (Mismas que en Usuario para consistencia)
    const ESTADO_URL_INCORRECTA = 1;
    const ESTADO_EXISTENCIA_RECURSO = 3;
    const ESTADO_CREACION_EXITOSA = 2;
    const ESTADO_CREACION_FALLIDA = 3;
    const ESTADO_ACTUALIZACION_EXITOSA = 4;
    const ESTADO_ACTUALIZACION_FALLIDA = 5;
    const ESTADO_ELIMINACION_EXITOSA = 6;
    const ESTADO_ELIMINACION_FALLIDA = 7;
    const ESTADO_EXITO = 8;
    const ESTADO_ERROR_BD = 9;
    const ESTADO_PARAMETROS_INCORRECTOS = 10;
    const ESTADO_NO_ENCONTRADO = 14;

    // ==========================================================
    // MÉTODOS PÚBLICOS (API)
    // ==========================================================

    /**
     * Obtiene proyectos.
     * GET /proyecto       -> Lista todos los proyectos del usuario
     * GET /proyecto/{id}  -> Obtiene un proyecto específico
     */
    public static function get($parameters)
    {
        // 1. AUTORIZACIÓN: Obtenemos el ID del usuario logueado
        $idUsuario = Usuario::autorizar();

        if (isset($parameters[0])) {
            return self::obtenerProyectoPorId($idUsuario, $parameters[0]);
        } else {
            return self::obtenerProyectos($idUsuario);
        }
    }

    /**
     * Crea un nuevo proyecto.
     * POST /proyecto
     * Body: { "nombre": "...", "descripcion": "..." }
     */
    public static function post($parameters)
    {
        $idUsuario = Usuario::autorizar();

        $body = file_get_contents('php://input');
        $proyecto = json_decode($body);

        return self::crearProyecto($idUsuario, $proyecto);
    }

    /**
     * Actualiza un proyecto existente.
     * PUT /proyecto/{id}
     * Body: { "nombre": "...", "descripcion": "...", "estado": "..." }
     */
    public static function put($parameters)
    {
        $idUsuario = Usuario::autorizar();

        if (!isset($parameters[0])) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Falta el ID del proyecto", 400);
        }

        $body = file_get_contents('php://input');
        $proyecto = json_decode($body);

        return self::actualizarProyecto($idUsuario, $parameters[0], $proyecto);
    }

    /**
     * Elimina un proyecto.
     * DELETE /proyecto/{id}
     */
    public static function delete($parameters)
    {
        $idUsuario = Usuario::autorizar();

        if (!isset($parameters[0])) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Falta el ID del proyecto", 400);
        }

        return self::eliminarProyecto($idUsuario, $parameters[0]);
    }

    // ==========================================================
    // MÉTODOS PRIVADOS (Lógica de Negocio y BD)
    // ==========================================================

    private static function obtenerProyectos($idUsuario)
    {
        // Solo seleccionamos los proyectos QUE PERTENECEN al usuario logueado
        $comando = "SELECT " .
            self::ID . ", " .
            self::NOMBRE . ", " .
            self::DESCRIPCION . ", " .
            self::ESTADO_PROYECTO . ", " .
            self::CREATED_AT .
            " FROM " . self::NOMBRE_TABLA .
            " WHERE " . self::ID_USUARIO . " = ?";

        try {
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $idUsuario);

            if ($sentencia->execute()) {
                $resultado = $sentencia->fetchAll(PDO::FETCH_ASSOC);
                http_response_code(200);
                return ["estado" => self::ESTADO_EXITO, "datos" => $resultado];
            } else {
                throw new ExcepcionApi(self::ESTADO_ERROR_BD, "Error al consultar la BD", 500);
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    private static function obtenerProyectoPorId($idUsuario, $idProyecto)
    {
        $comando = "SELECT " .
            self::ID . ", " .
            self::NOMBRE . ", " .
            self::DESCRIPCION . ", " .
            self::ESTADO_PROYECTO . ", " .
            self::CREATED_AT .
            " FROM " . self::NOMBRE_TABLA .
            " WHERE " . self::ID . " = ? AND " . self::ID_USUARIO . " = ?";

        try {
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $idProyecto);
            $sentencia->bindParam(2, $idUsuario);

            if ($sentencia->execute()) {
                $resultado = $sentencia->fetch(PDO::FETCH_ASSOC);
                if ($resultado) {
                    http_response_code(200);
                    return ["estado" => self::ESTADO_EXITO, "datos" => $resultado];
                } else {
                    throw new ExcepcionApi(self::ESTADO_NO_ENCONTRADO, "El proyecto no existe o no te pertenece", 404);
                }
            } else {
                throw new ExcepcionApi(self::ESTADO_ERROR_BD, "Error al consultar la BD", 500);
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    private static function crearProyecto($idUsuario, $proyecto)
    {
        if (json_last_error() != JSON_ERROR_NONE || is_null($proyecto)) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "JSON Inválido", 400);
        }

        // Validación mínima: Solo el nombre es estrictamente obligatorio según tu BD
        if (!isset($proyecto->nombre)) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Falta el nombre del proyecto", 400);
        }

        $nombre = $proyecto->nombre;
        // Descripción y estado son opcionales en BD (tienen defaults o nulls), pero los manejamos
        $descripcion = isset($proyecto->descripcion) ? $proyecto->descripcion : null;
        $estado = isset($proyecto->estado) ? $proyecto->estado : 'Pendiente'; // Valor por defecto

        try {
            $comando = "INSERT INTO " . self::NOMBRE_TABLA . " ( " .
                self::NOMBRE . ", " .
                self::DESCRIPCION . ", " .
                self::ESTADO_PROYECTO . ", " .
                self::ID_USUARIO . ")" .
                " VALUES (?, ?, ?, ?)";

            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $nombre);
            $sentencia->bindParam(2, $descripcion);
            $sentencia->bindParam(3, $estado);
            $sentencia->bindParam(4, $idUsuario);

            $resultado = $sentencia->execute();

            if ($resultado) {
                http_response_code(201); // 201 Created
                return [
                    "estado" => self::ESTADO_CREACION_EXITOSA,
                    "mensaje" => "Proyecto creado",
                    "id" => ConexionBD::obtenerInstancia()->obtenerBD()->lastInsertId()
                ];
            } else {
                return self::ESTADO_CREACION_FALLIDA;
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    private static function actualizarProyecto($idUsuario, $idProyecto, $proyecto)
    {
        if (json_last_error() != JSON_ERROR_NONE || is_null($proyecto)) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "JSON Inválido", 400);
        }

        // Construcción dinámica de la consulta UPDATE
        // Esto permite actualizar solo el nombre, solo el estado, o ambos.
        $campos = [];
        $valores = [];

        if (isset($proyecto->nombre)) {
            $campos[] = self::NOMBRE . " = ?";
            $valores[] = $proyecto->nombre;
        }
        if (isset($proyecto->descripcion)) {
            $campos[] = self::DESCRIPCION . " = ?";
            $valores[] = $proyecto->descripcion;
        }
        if (isset($proyecto->estado)) {
            $campos[] = self::ESTADO_PROYECTO . " = ?";
            $valores[] = $proyecto->estado;
        }

        if (empty($campos)) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No se enviaron campos para actualizar", 400);
        }

        // Añadimos las condiciones WHERE al final
        $valores[] = $idProyecto;
        $valores[] = $idUsuario;

        $comando = "UPDATE " . self::NOMBRE_TABLA .
            " SET " . implode(", ", $campos) .
            " WHERE " . self::ID . " = ? AND " . self::ID_USUARIO . " = ?";

        try {
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);

            // Bind manual de parámetros porque es un array dinámico
            for ($i = 0; $i < count($valores); $i++) {
                $sentencia->bindValue($i + 1, $valores[$i]);
            }

            if ($sentencia->execute()) {
                if ($sentencia->rowCount() > 0) {
                    http_response_code(200);
                    return ["estado" => self::ESTADO_ACTUALIZACION_EXITOSA, "mensaje" => "Proyecto actualizado"];
                } else {
                    // Si rowCount es 0, puede ser que los datos sean iguales o que el proyecto no exista/no sea del usuario
                    // Verificamos existencia para dar un mensaje más preciso
                    return ["estado" => self::ESTADO_ACTUALIZACION_EXITOSA, "mensaje" => "No hubo cambios o proyecto no encontrado"];
                }
            } else {
                throw new ExcepcionApi(self::ESTADO_ACTUALIZACION_FALLIDA, "Error al actualizar", 500);
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    private static function eliminarProyecto($idUsuario, $idProyecto)
    {
        // Gracias al ON DELETE CASCADE de la BD, al borrar el proyecto se borran tareas y subtareas solas.
        $comando = "DELETE FROM " . self::NOMBRE_TABLA .
            " WHERE " . self::ID . " = ? AND " . self::ID_USUARIO . " = ?";

        try {
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $idProyecto);
            $sentencia->bindParam(2, $idUsuario);

            if ($sentencia->execute()) {
                if ($sentencia->rowCount() > 0) {
                    http_response_code(200);
                    return ["estado" => self::ESTADO_ELIMINACION_EXITOSA, "mensaje" => "Proyecto eliminado"];
                } else {
                    throw new ExcepcionApi(self::ESTADO_NO_ENCONTRADO, "El proyecto no existe o no te pertenece", 404);
                }
            } else {
                throw new ExcepcionApi(self::ESTADO_ELIMINACION_FALLIDA, "Error al eliminar", 500);
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }
}
?>