<?php
require_once "../config/conexionBD.php";
require_once "usuario.php"; // Necesario para autorización

class Tarea
{
    // Constantes de las tablas
    const NOMBRE_TABLA = "tareas";
    const TABLA_PROYECTOS = "proyectos"; // Necesaria para validar propiedad

    // Columnas de la tabla Tareas
    const ID = "id";
    const NOMBRE = "nombre";
    const DESCRIPCION = "descripcion";
    const ESTADO_TAREA = "estado";
    const FECHA_VENCIMIENTO = "fecha_vencimiento";
    const ID_PROYECTO = "id_proyecto";
    const CREATED_AT = "created_at";

    // Constantes de estado (Idénticas al estándar del proyecto)
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
     * Obtiene tareas.
     * GET /tarea                 -> Todas las tareas de mis proyectos
     * GET /tarea?id_proyecto=5   -> Tareas de un proyecto específico (Opcional pero útil)
     * GET /tarea/{id}            -> Una tarea específica
     */
    public static function get($parameters)
    {
        $idUsuario = Usuario::autorizar();

        if (isset($parameters[0])) {
            return self::obtenerTareaPorId($idUsuario, $parameters[0]);
        }

        // Extra: Permitir filtrar por proyecto si viene en la URL (?id_proyecto=X)
        if (isset($_GET['id_proyecto'])) {
            return self::obtenerTareasPorProyecto($idUsuario, $_GET['id_proyecto']);
        }

        return self::obtenerTodasLasTareas($idUsuario);
    }

    /**
     * Crea una nueva tarea.
     * POST /tarea
     * Body: { "nombre": "...", "id_proyecto": 1, ... }
     */
    public static function post($parameters)
    {
        $idUsuario = Usuario::autorizar();

        $body = file_get_contents('php://input');
        $tarea = json_decode($body);

        return self::crearTarea($idUsuario, $tarea);
    }

    /**
     * Actualiza una tarea.
     * PUT /tarea/{id}
     */
    public static function put($parameters)
    {
        $idUsuario = Usuario::autorizar();

        if (!isset($parameters[0])) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Falta el ID de la tarea", 400);
        }

        $body = file_get_contents('php://input');
        $tarea = json_decode($body);

        return self::actualizarTarea($idUsuario, $parameters[0], $tarea);
    }

    /**
     * Elimina una tarea.
     * DELETE /tarea/{id}
     */
    public static function delete($parameters)
    {
        $idUsuario = Usuario::autorizar();

        if (!isset($parameters[0])) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Falta el ID de la tarea", 400);
        }

        return self::eliminarTarea($idUsuario, $parameters[0]);
    }

    // ==========================================================
    // MÉTODOS PRIVADOS
    // ==========================================================

    private static function obtenerTodasLasTareas($idUsuario)
    {
        // JOIN VITAL: Solo mostramos tareas cuyos proyectos pertenezcan al usuario
        $comando = "SELECT t.* FROM " . self::NOMBRE_TABLA . " t" .
            " INNER JOIN " . self::TABLA_PROYECTOS . " p ON t." . self::ID_PROYECTO . " = p.id" .
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

    private static function obtenerTareasPorProyecto($idUsuario, $idProyecto)
    {
        // Validamos que el proyecto sea del usuario Y traemos sus tareas
        $comando = "SELECT t.* FROM " . self::NOMBRE_TABLA . " t" .
            " INNER JOIN " . self::TABLA_PROYECTOS . " p ON t." . self::ID_PROYECTO . " = p.id" .
            " WHERE p.id_usuario = ? AND p.id = ?";

        try {
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $idUsuario);
            $sentencia->bindParam(2, $idProyecto);
            $sentencia->execute();

            $resultado = $sentencia->fetchAll(PDO::FETCH_ASSOC);
            http_response_code(200);
            return ["estado" => self::ESTADO_EXITO, "datos" => $resultado];
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    private static function obtenerTareaPorId($idUsuario, $idTarea)
    {
        $comando = "SELECT t.* FROM " . self::NOMBRE_TABLA . " t" .
            " INNER JOIN " . self::TABLA_PROYECTOS . " p ON t." . self::ID_PROYECTO . " = p.id" .
            " WHERE t." . self::ID . " = ? AND p.id_usuario = ?";

        try {
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $idTarea);
            $sentencia->bindParam(2, $idUsuario);

            if ($sentencia->execute()) {
                $resultado = $sentencia->fetch(PDO::FETCH_ASSOC);
                if ($resultado) {
                    http_response_code(200);
                    return ["estado" => self::ESTADO_EXITO, "datos" => $resultado];
                } else {
                    throw new ExcepcionApi(self::ESTADO_NO_ENCONTRADO, "La tarea no existe o no tienes permiso", 404);
                }
            } else {
                throw new ExcepcionApi(self::ESTADO_ERROR_BD, "Error al consultar BD", 500);
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    private static function crearTarea($idUsuario, $tarea)
    {
        if (json_last_error() != JSON_ERROR_NONE || is_null($tarea)) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "JSON Inválido", 400);
        }

        // Validación: Nombre e ID de Proyecto son obligatorios
        if (!isset($tarea->nombre) || !isset($tarea->id_proyecto)) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Faltan datos: nombre o id_proyecto", 400);
        }

        // SEGURIDAD: Verificar que el proyecto pertenece al usuario antes de insertar
        if (!self::validarPropiedadProyecto($idUsuario, $tarea->id_proyecto)) {
            throw new ExcepcionApi(self::ESTADO_NO_ENCONTRADO, "El proyecto indicado no existe o no te pertenece", 403);
        }

        $nombre = $tarea->nombre;
        $idProyecto = $tarea->id_proyecto;
        $descripcion = isset($tarea->descripcion) ? $tarea->descripcion : null;
        $estado = isset($tarea->estado) ? $tarea->estado : 'Pendiente';
        $fechaVencimiento = isset($tarea->fecha_vencimiento) ? $tarea->fecha_vencimiento : null;

        try {
            $comando = "INSERT INTO " . self::NOMBRE_TABLA . " (" .
                self::NOMBRE . ", " .
                self::DESCRIPCION . ", " .
                self::ESTADO_TAREA . ", " .
                self::FECHA_VENCIMIENTO . ", " .
                self::ID_PROYECTO . ")" .
                " VALUES (?, ?, ?, ?, ?)";

            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $nombre);
            $sentencia->bindParam(2, $descripcion);
            $sentencia->bindParam(3, $estado);
            $sentencia->bindParam(4, $fechaVencimiento);
            $sentencia->bindParam(5, $idProyecto);

            if ($sentencia->execute()) {
                http_response_code(201);
                return [
                    "estado" => self::ESTADO_CREACION_EXITOSA,
                    "mensaje" => "Tarea creada",
                    "id" => ConexionBD::obtenerInstancia()->obtenerBD()->lastInsertId()
                ];
            } else {
                return self::ESTADO_CREACION_FALLIDA;
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    private static function actualizarTarea($idUsuario, $idTarea, $tarea)
    {
        if (json_last_error() != JSON_ERROR_NONE || is_null($tarea)) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "JSON Inválido", 400);
        }

        $campos = [];
        $valores = [];

        // Construcción dinámica (igual que en Proyecto)
        if (isset($tarea->nombre)) {
            $campos[] = "t." . self::NOMBRE . " = ?";
            $valores[] = $tarea->nombre;
        }
        if (isset($tarea->descripcion)) {
            $campos[] = "t." . self::DESCRIPCION . " = ?";
            $valores[] = $tarea->descripcion;
        }
        if (isset($tarea->estado)) {
            $campos[] = "t." . self::ESTADO_TAREA . " = ?";
            $valores[] = $tarea->estado;
        }
        if (isset($tarea->fecha_vencimiento)) {
            $campos[] = "t." . self::FECHA_VENCIMIENTO . " = ?";
            $valores[] = $tarea->fecha_vencimiento;
        }

        if (empty($campos)) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "No se enviaron campos", 400);
        }

        $valores[] = $idTarea;
        $valores[] = $idUsuario;

        // UPDATE con JOIN para asegurar propiedad en una sola consulta
        $comando = "UPDATE " . self::NOMBRE_TABLA . " t" .
            " INNER JOIN " . self::TABLA_PROYECTOS . " p ON t." . self::ID_PROYECTO . " = p.id" .
            " SET " . implode(", ", $campos) .
            " WHERE t." . self::ID . " = ? AND p.id_usuario = ?";

        try {
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);

            for ($i = 0; $i < count($valores); $i++) {
                $sentencia->bindValue($i + 1, $valores[$i]);
            }

            if ($sentencia->execute()) {
                if ($sentencia->rowCount() > 0) {
                    http_response_code(200);
                    return ["estado" => self::ESTADO_ACTUALIZACION_EXITOSA, "mensaje" => "Tarea actualizada"];
                } else {
                    return ["estado" => self::ESTADO_ACTUALIZACION_EXITOSA, "mensaje" => "No hubo cambios o tarea no encontrada"];
                }
            } else {
                throw new ExcepcionApi(self::ESTADO_ACTUALIZACION_FALLIDA, "Error al actualizar", 500);
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    private static function eliminarTarea($idUsuario, $idTarea)
    {
        // DELETE con JOIN para asegurar propiedad
        // "DELETE t" indica que solo borramos de la tabla 'tareas', no del proyecto
        $comando = "DELETE t FROM " . self::NOMBRE_TABLA . " t" .
            " INNER JOIN " . self::TABLA_PROYECTOS . " p ON t." . self::ID_PROYECTO . " = p.id" .
            " WHERE t." . self::ID . " = ? AND p.id_usuario = ?";

        try {
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $idTarea);
            $sentencia->bindParam(2, $idUsuario);

            if ($sentencia->execute()) {
                if ($sentencia->rowCount() > 0) {
                    http_response_code(200);
                    return ["estado" => self::ESTADO_ELIMINACION_EXITOSA, "mensaje" => "Tarea eliminada"];
                } else {
                    throw new ExcepcionApi(self::ESTADO_NO_ENCONTRADO, "La tarea no existe o no te pertenece", 404);
                }
            } else {
                throw new ExcepcionApi(self::ESTADO_ELIMINACION_FALLIDA, "Error al eliminar", 500);
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    // Método auxiliar para verificar propiedad de proyecto antes de insertar
    private static function validarPropiedadProyecto($idUsuario, $idProyecto)
    {
        $comando = "SELECT COUNT(*) FROM " . self::TABLA_PROYECTOS .
            " WHERE id = ? AND id_usuario = ?";

        $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
        $sentencia->bindParam(1, $idProyecto);
        $sentencia->bindParam(2, $idUsuario);
        $sentencia->execute();

        return $sentencia->fetchColumn() > 0;
    }
}
?>