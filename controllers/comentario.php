<?php
require_once "../config/conexionBD.php";
require_once "usuario.php"; // Necesario para autorización

class Comentario
{
    // Constantes de las tablas
    const NOMBRE_TABLA = "comentarios";
    const TABLA_PROYECTOS = "proyectos";
    const TABLA_TAREAS = "tareas";
    const TABLA_SUBTAREAS = "subtareas";

    // Columnas de la tabla Comentarios
    const ID = "id";
    const CONTENIDO = "contenido";
    const ID_USUARIO = "id_usuario";
    const ELEMENTO_ID = "elemento_id";
    const ELEMENTO_TIPO = "elemento_tipo";
    const CREATED_AT = "created_at";

    // Tipos permitidos
    const TIPO_PROYECTO = 'proyecto';
    const TIPO_TAREA = 'tarea';
    const TIPO_SUBTAREA = 'subtarea';

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
     * Obtiene comentarios.
     * GET /comentario?tipo=proyecto&id=5  -> Comentarios del proyecto 5
     * GET /comentario?tipo=tarea&id=10    -> Comentarios de la tarea 10
     */
    public static function get($parameters)
    {
        $idUsuario = Usuario::autorizar();

        // Validamos que vengan los parámetros de filtro obligatorios para GET
        if (!isset($_GET['tipo']) || !isset($_GET['id'])) {
            throw new ExcepcionApi(
                self::ESTADO_PARAMETROS_INCORRECTOS,
                "Debes especificar 'tipo' (proyecto, tarea, subtarea) e 'id' del elemento para ver sus comentarios.",
                400
            );
        }

        $tipo = strtolower($_GET['tipo']);
        $idElemento = $_GET['id'];

        return self::obtenerComentariosPorElemento($idUsuario, $idElemento, $tipo);
    }

    /**
     * Crea un nuevo comentario.
     * POST /comentario
     * Body: { "contenido": "...", "elemento_id": 5, "elemento_tipo": "proyecto" }
     */
    public static function post($parameters)
    {
        $idUsuario = Usuario::autorizar();

        $body = file_get_contents('php://input');
        $comentario = json_decode($body);

        return self::crearComentario($idUsuario, $comentario);
    }

    /**
     * Actualiza un comentario.
     * PUT /comentario/{id}
     * Body: { "contenido": "Texto editado..." }
     */
    public static function put($parameters)
    {
        $idUsuario = Usuario::autorizar();

        if (!isset($parameters[0])) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Falta el ID del comentario", 400);
        }

        $body = file_get_contents('php://input');
        $comentario = json_decode($body);

        return self::actualizarComentario($idUsuario, $parameters[0], $comentario);
    }

    /**
     * Elimina un comentario.
     * DELETE /comentario/{id}
     */
    public static function delete($parameters)
    {
        $idUsuario = Usuario::autorizar();

        if (!isset($parameters[0])) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Falta el ID del comentario", 400);
        }

        return self::eliminarComentario($idUsuario, $parameters[0]);
    }

    // ==========================================================
    // MÉTODOS PRIVADOS
    // ==========================================================

    private static function obtenerComentariosPorElemento($idUsuario, $idElemento, $tipo)
    {
        // 1. SEGURIDAD: Antes de devolver nada, verificamos que el usuario tenga acceso al elemento padre
        if (!self::validarPropiedadElemento($idUsuario, $idElemento, $tipo)) {
            throw new ExcepcionApi(self::ESTADO_NO_ENCONTRADO, "El elemento no existe o no tienes permiso para ver sus comentarios", 403);
        }

        // Si tiene permiso, traemos los comentarios con los datos del autor (JOIN usuarios)
        // Esto es útil para mostrar "Juan dijo: ..." en el Front
        $comando = "SELECT c.*, u.nombre as autor_nombre FROM " . self::NOMBRE_TABLA . " c" .
            " INNER JOIN usuarios u ON c.id_usuario = u.id" .
            " WHERE c." . self::ELEMENTO_ID . " = ? AND c." . self::ELEMENTO_TIPO . " = ?" .
            " ORDER BY c." . self::CREATED_AT . " ASC"; // Orden cronológico

        try {
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $idElemento);
            $sentencia->bindParam(2, $tipo);
            $sentencia->execute();

            $resultado = $sentencia->fetchAll(PDO::FETCH_ASSOC);
            http_response_code(200);
            return ["estado" => self::ESTADO_EXITO, "datos" => $resultado];
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    private static function crearComentario($idUsuario, $comentario)
    {
        if (json_last_error() != JSON_ERROR_NONE || is_null($comentario)) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "JSON Inválido", 400);
        }

        if (!isset($comentario->contenido) || !isset($comentario->elemento_id) || !isset($comentario->elemento_tipo)) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Faltan datos: contenido, elemento_id o elemento_tipo", 400);
        }

        $tipo = strtolower($comentario->elemento_tipo);

        // Validación de tipos permitidos
        if (!in_array($tipo, [self::TIPO_PROYECTO, self::TIPO_TAREA, self::TIPO_SUBTAREA])) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Tipo de elemento no válido", 400);
        }

        // 2. SEGURIDAD: Verificar que el usuario es dueño del lugar donde quiere comentar
        if (!self::validarPropiedadElemento($idUsuario, $comentario->elemento_id, $tipo)) {
            throw new ExcepcionApi(self::ESTADO_NO_ENCONTRADO, "No puedes comentar en un elemento que no existe o no te pertenece", 403);
        }

        try {
            $comando = "INSERT INTO " . self::NOMBRE_TABLA . " (" .
                self::CONTENIDO . ", " .
                self::ID_USUARIO . ", " .
                self::ELEMENTO_ID . ", " .
                self::ELEMENTO_TIPO . ")" .
                " VALUES (?, ?, ?, ?)";

            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $comentario->contenido);
            $sentencia->bindParam(2, $idUsuario);
            $sentencia->bindParam(3, $comentario->elemento_id);
            $sentencia->bindParam(4, $tipo);

            if ($sentencia->execute()) {
                http_response_code(201);
                return [
                    "estado" => self::ESTADO_CREACION_EXITOSA,
                    "mensaje" => "Comentario agregado",
                    "id" => ConexionBD::obtenerInstancia()->obtenerBD()->lastInsertId()
                ];
            } else {
                return self::ESTADO_CREACION_FALLIDA;
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    private static function actualizarComentario($idUsuario, $idComentario, $comentarioBody)
    {
        if (json_last_error() != JSON_ERROR_NONE || is_null($comentarioBody)) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "JSON Inválido", 400);
        }

        if (!isset($comentarioBody->contenido)) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Falta el contenido", 400);
        }

        // UPDATE simple: Solo permitimos editar si el ID del comentario coincide y EL USUARIO ES EL AUTOR
        $comando = "UPDATE " . self::NOMBRE_TABLA .
            " SET " . self::CONTENIDO . " = ? " .
            " WHERE " . self::ID . " = ? AND " . self::ID_USUARIO . " = ?";

        try {
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $comentarioBody->contenido);
            $sentencia->bindParam(2, $idComentario);
            $sentencia->bindParam(3, $idUsuario);

            if ($sentencia->execute()) {
                if ($sentencia->rowCount() > 0) {
                    http_response_code(200);
                    return ["estado" => self::ESTADO_ACTUALIZACION_EXITOSA, "mensaje" => "Comentario actualizado"];
                } else {
                    // Si no hubo cambios, puede ser porque no es su comentario
                    throw new ExcepcionApi(self::ESTADO_NO_ENCONTRADO, "No se encontró el comentario o no eres el autor", 404);
                }
            } else {
                throw new ExcepcionApi(self::ESTADO_ACTUALIZACION_FALLIDA, "Error al actualizar", 500);
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    private static function eliminarComentario($idUsuario, $idComentario)
    {
        // DELETE simple: Solo si es el autor
        $comando = "DELETE FROM " . self::NOMBRE_TABLA .
            " WHERE " . self::ID . " = ? AND " . self::ID_USUARIO . " = ?";

        try {
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $idComentario);
            $sentencia->bindParam(2, $idUsuario);

            if ($sentencia->execute()) {
                if ($sentencia->rowCount() > 0) {
                    http_response_code(200);
                    return ["estado" => self::ESTADO_ELIMINACION_EXITOSA, "mensaje" => "Comentario eliminado"];
                } else {
                    throw new ExcepcionApi(self::ESTADO_NO_ENCONTRADO, "No se encontró el comentario o no eres el autor", 404);
                }
            } else {
                throw new ExcepcionApi(self::ESTADO_ELIMINACION_FALLIDA, "Error al eliminar", 500);
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    /**
     * Helper Polimórfico: Verifica si el usuario es dueño del Proyecto, Tarea o Subtarea
     */
    private static function validarPropiedadElemento($idUsuario, $idElemento, $tipo)
    {
        $comando = "";

        switch ($tipo) {
            case self::TIPO_PROYECTO:
                // Directo: Proyectos del usuario
                $comando = "SELECT COUNT(*) FROM " . self::TABLA_PROYECTOS .
                    " WHERE id = ? AND id_usuario = ?";
                break;

            case self::TIPO_TAREA:
                // 1 Nivel: Tarea -> Proyecto -> Usuario
                $comando = "SELECT COUNT(*) FROM " . self::TABLA_TAREAS . " t" .
                    " INNER JOIN " . self::TABLA_PROYECTOS . " p ON t.id_proyecto = p.id" .
                    " WHERE t.id = ? AND p.id_usuario = ?";
                break;

            case self::TIPO_SUBTAREA:
                // 2 Niveles: Subtarea -> Tarea -> Proyecto -> Usuario
                $comando = "SELECT COUNT(*) FROM " . self::TABLA_SUBTAREAS . " s" .
                    " INNER JOIN " . self::TABLA_TAREAS . " t ON s.id_tarea = t.id" .
                    " INNER JOIN " . self::TABLA_PROYECTOS . " p ON t.id_proyecto = p.id" .
                    " WHERE s.id = ? AND p.id_usuario = ?";
                break;

            default:
                return false;
        }

        $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
        $sentencia->bindParam(1, $idElemento);
        $sentencia->bindParam(2, $idUsuario);
        $sentencia->execute();

        return $sentencia->fetchColumn() > 0;
    }
}
?>