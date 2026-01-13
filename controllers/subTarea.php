<?php
require_once "../config/conexionBD.php";
require_once "usuario.php"; // Necesario para autorización

class SubTarea
{
    // Constantes de las tablas
    const NOMBRE_TABLA = "subtareas";
    const TABLA_TAREAS = "tareas";
    const TABLA_PROYECTOS = "proyectos";

    // Columnas de la tabla Subtareas
    const ID = "id";
    const NOMBRE = "nombre";
    const ESTADO_SUBTAREA = "estado";
    const ID_TAREA = "id_tarea";
    const CREATED_AT = "created_at";

    // Constantes de estado
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
     * Obtiene subtareas.
     * GET /subtarea                 -> Todas las subtareas de mis proyectos
     * GET /subtarea?id_tarea=10     -> Subtareas de una tarea específica
     * GET /subtarea/{id}            -> Una subtarea específica
     */
    public static function get($parameters)
    {
        $idUsuario = Usuario::autorizar();

        if (isset($parameters[0])) {
            return self::obtenerSubTareaPorId($idUsuario, $parameters[0]);
        }

        if (isset($_GET['id_tarea'])) {
            return self::obtenerSubTareasPorTarea($idUsuario, $_GET['id_tarea']);
        }

        return self::obtenerTodasLasSubTareas($idUsuario);
    }

    /**
     * Crea una nueva subtarea.
     * POST /subtarea
     * Body: { "nombre": "...", "id_tarea": 10 }
     */
    public static function post($parameters)
    {
        $idUsuario = Usuario::autorizar();

        $body = file_get_contents('php://input');
        $subtarea = json_decode($body);

        return self::crearSubTarea($idUsuario, $subtarea);
    }

    /**
     * Actualiza una subtarea.
     * PUT /subtarea/{id}
     * Body: { "nombre": "...", "estado": "Completado" }
     */
    public static function put($parameters)
    {
        $idUsuario = Usuario::autorizar();

        if (!isset($parameters[0])) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Falta el ID de la subtarea", 400);
        }

        $body = file_get_contents('php://input');
        $subtarea = json_decode($body);

        return self::actualizarSubTarea($idUsuario, $parameters[0], $subtarea);
    }

    /**
     * Elimina una subtarea.
     * DELETE /subtarea/{id}
     */
    public static function delete($parameters)
    {
        $idUsuario = Usuario::autorizar();

        if (!isset($parameters[0])) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Falta el ID de la subtarea", 400);
        }

        return self::eliminarSubTarea($idUsuario, $parameters[0]);
    }

    // ==========================================================
    // MÉTODOS PRIVADOS
    // ==========================================================

    private static function obtenerTodasLasSubTareas($idUsuario)
    {
        // Doble JOIN: Subtarea -> Tarea -> Proyecto -> Usuario
        $comando = "SELECT s.* FROM " . self::NOMBRE_TABLA . " s" .
            " INNER JOIN " . self::TABLA_TAREAS . " t ON s." . self::ID_TAREA . " = t.id" .
            " INNER JOIN " . self::TABLA_PROYECTOS . " p ON t.id_proyecto = p.id" .
            " WHERE p.id_usuario = ?";

        try {
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $idUsuario);
            $sentencia->execute();

            $resultado = $sentencia->fetchAll(PDO::FETCH_ASSOC);
            http_response_code(200);
            return ["estado" => self::ESTADO_EXITO, "datos" => $resultado];
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    private static function obtenerSubTareasPorTarea($idUsuario, $idTarea)
    {
        // Validamos que la tarea pertenezca a un proyecto del usuario
        $comando = "SELECT s.* FROM " . self::NOMBRE_TABLA . " s" .
            " INNER JOIN " . self::TABLA_TAREAS . " t ON s." . self::ID_TAREA . " = t.id" .
            " INNER JOIN " . self::TABLA_PROYECTOS . " p ON t.id_proyecto = p.id" .
            " WHERE p.id_usuario = ? AND t.id = ?";

        try {
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $idUsuario);
            $sentencia->bindParam(2, $idTarea);
            $sentencia->execute();

            $resultado = $sentencia->fetchAll(PDO::FETCH_ASSOC);
            http_response_code(200);
            return ["estado" => self::ESTADO_EXITO, "datos" => $resultado];
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    private static function obtenerSubTareaPorId($idUsuario, $idSubTarea)
    {
        $comando = "SELECT s.* FROM " . self::NOMBRE_TABLA . " s" .
            " INNER JOIN " . self::TABLA_TAREAS . " t ON s." . self::ID_TAREA . " = t.id" .
            " INNER JOIN " . self::TABLA_PROYECTOS . " p ON t.id_proyecto = p.id" .
            " WHERE s." . self::ID . " = ? AND p.id_usuario = ?";

        try {
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $idSubTarea);
            $sentencia->bindParam(2, $idUsuario);

            if ($sentencia->execute()) {
                $resultado = $sentencia->fetch(PDO::FETCH_ASSOC);
                if ($resultado) {
                    http_response_code(200);
                    return ["estado" => self::ESTADO_EXITO, "datos" => $resultado];
                } else {
                    throw new ExcepcionApi(self::ESTADO_NO_ENCONTRADO, "La subtarea no existe o no tienes permiso", 404);
                }
            } else {
                throw new ExcepcionApi(self::ESTADO_ERROR_BD, "Error al consultar BD", 500);
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    private static function crearSubTarea($idUsuario, $subtarea)
    {
        if (json_last_error() != JSON_ERROR_NONE || is_null($subtarea)) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "JSON Inválido", 400);
        }

        if (!isset($subtarea->nombre) || !isset($subtarea->id_tarea)) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Faltan datos: nombre o id_tarea", 400);
        }

        // SEGURIDAD: Verificar que la Tarea padre pertenece a un Proyecto del Usuario
        if (!self::validarPropiedadTarea($idUsuario, $subtarea->id_tarea)) {
            throw new ExcepcionApi(self::ESTADO_NO_ENCONTRADO, "La tarea indicada no existe o no te pertenece", 403);
        }

        $nombre = $subtarea->nombre;
        $idTarea = $subtarea->id_tarea;
        $estado = isset($subtarea->estado) ? $subtarea->estado : 'Pendiente';

        try {
            $comando = "INSERT INTO " . self::NOMBRE_TABLA . " (" .
                self::NOMBRE . ", " .
                self::ESTADO_SUBTAREA . ", " .
                self::ID_TAREA . ")" .
                " VALUES (?, ?, ?)";

            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $nombre);
            $sentencia->bindParam(2, $estado);
            $sentencia->bindParam(3, $idTarea);

            if ($sentencia->execute()) {
                http_response_code(201);
                return [
                    "estado" => self::ESTADO_CREACION_EXITOSA,
                    "mensaje" => "Subtarea creada",
                    "id" => ConexionBD::obtenerInstancia()->obtenerBD()->lastInsertId()
                ];
            } else {
                return self::ESTADO_CREACION_FALLIDA;
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    private static function actualizarSubTarea($idUsuario, $idSubTarea, $subtarea)
    {
        if (json_last_error() != JSON_ERROR_NONE || is_null($subtarea)) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "JSON Inválido", 400);
        }

        $campos = [];
        $valores = [];

        if (isset($subtarea->nombre)) {
            $campos[] = "s." . self::NOMBRE . " = ?";
            $valores[] = $subtarea->nombre;
        }
        if (isset($subtarea->estado)) {
            $campos[] = "s." . self::ESTADO_SUBTAREA . " = ?";
            $valores[] = $subtarea->estado;
        }

        if (empty($campos)) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No se enviaron campos", 400);
        }

        $valores[] = $idSubTarea;
        $valores[] = $idUsuario;

        // UPDATE con doble JOIN para asegurar propiedad
        $comando = "UPDATE " . self::NOMBRE_TABLA . " s" .
            " INNER JOIN " . self::TABLA_TAREAS . " t ON s." . self::ID_TAREA . " = t.id" .
            " INNER JOIN " . self::TABLA_PROYECTOS . " p ON t.id_proyecto = p.id" .
            " SET " . implode(", ", $campos) .
            " WHERE s." . self::ID . " = ? AND p.id_usuario = ?";

        try {
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);

            for ($i = 0; $i < count($valores); $i++) {
                $sentencia->bindValue($i + 1, $valores[$i]);
            }

            if ($sentencia->execute()) {
                if ($sentencia->rowCount() > 0) {
                    http_response_code(200);
                    return ["estado" => self::ESTADO_ACTUALIZACION_EXITOSA, "mensaje" => "Subtarea actualizada"];
                } else {
                    return ["estado" => self::ESTADO_ACTUALIZACION_EXITOSA, "mensaje" => "No hubo cambios o subtarea no encontrada"];
                }
            } else {
                throw new ExcepcionApi(self::ESTADO_ACTUALIZACION_FALLIDA, "Error al actualizar", 500);
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    private static function eliminarSubTarea($idUsuario, $idSubTarea)
    {
        // DELETE con JOIN para asegurar propiedad (Subtarea -> Tarea -> Proyecto -> Usuario)
        $comando = "DELETE s FROM " . self::NOMBRE_TABLA . " s" .
            " INNER JOIN " . self::TABLA_TAREAS . " t ON s." . self::ID_TAREA . " = t.id" .
            " INNER JOIN " . self::TABLA_PROYECTOS . " p ON t.id_proyecto = p.id" .
            " WHERE s." . self::ID . " = ? AND p.id_usuario = ?";

        try {
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $idSubTarea);
            $sentencia->bindParam(2, $idUsuario);

            if ($sentencia->execute()) {
                if ($sentencia->rowCount() > 0) {
                    http_response_code(200);
                    return ["estado" => self::ESTADO_ELIMINACION_EXITOSA, "mensaje" => "Subtarea eliminada"];
                } else {
                    throw new ExcepcionApi(self::ESTADO_NO_ENCONTRADO, "La subtarea no existe o no te pertenece", 404);
                }
            } else {
                throw new ExcepcionApi(self::ESTADO_ELIMINACION_FALLIDA, "Error al eliminar", 500);
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    // Valida que la tarea (padre) pertenezca a un proyecto del usuario
    private static function validarPropiedadTarea($idUsuario, $idTarea)
    {
        $comando = "SELECT COUNT(*) FROM " . self::TABLA_TAREAS . " t" .
            " INNER JOIN " . self::TABLA_PROYECTOS . " p ON t.id_proyecto = p.id" .
            " WHERE t.id = ? AND p.id_usuario = ?";

        $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
        $sentencia->bindParam(1, $idTarea);
        $sentencia->bindParam(2, $idUsuario);
        $sentencia->execute();

        return $sentencia->fetchColumn() > 0;
    }
}
?>