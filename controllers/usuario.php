<?php
require_once "../config/conexionBD.php";

class Usuario
{
    // Datos de la tabla de "usuarios"
    const NOMBRE_TABLA = "usuarios";
    const ID = "id";
    const NOMBRE = "nombre";
    const EMAIL = "email";
    const PASSWORD = "password";
    const API_TOKEN = "api_token";

    // Constantes de estado para respuestas y errores
    const ESTADO_URL_INCORRECTA = 1;
    const ESTADO_CREACION_EXITOSA = 2;
    const ESTADO_CREACION_FALLIDA = 3;
    const ESTADO_ACTUALIZACION_EXITOSA = 4;
    const ESTADO_ACTUALIZACION_FALLIDA = 5;
    const ESTADO_ELIMINACION_EXITOSA = 6;
    const ESTADO_ELIMINACION_FALLIDA = 7;
    const ESTADO_EXITO = 8;
    const ESTADO_ERROR_BD = 9;
    const ESTADO_PARAMETROS_INCORRECTOS = 10;
    const ESTADO_CLAVE_NO_AUTORIZADA = 11;
    const ESTADO_AUSENCIA_CLAVE_API = 12;
    const ESTADO_FALLA_DESCONOCIDO = 13;

    // Funcion para el metodo POST
    public static function post($parameters)
    {
        if ($parameters[0] == 'login') {
            return self::loguearUsuario();
        } else if ($parameters[0] == 'registro') {
            return self::registroUsuario();
        } else {
            throw new ExcepcionApi(self::ESTADO_URL_INCORRECTA, "No se reconoce la peticion", 400);
        }
    }

    private static function loguearUsuario()
    {
        $respuesta = array();
        $body = file_get_contents('php://input');
        $usuario = json_decode($body);

        $email = $usuario->email;
        $password = $usuario->password;

        if (self::autenticarUsuario($email, $password)) {
            $usuarioBD = self::obtenerUsuarioPorEmail($email);

            if ($usuarioBD != NULL) {
                http_response_code(200);
                $respuesta['id'] = $usuarioBD['id'];
                $respuesta['nombre'] = $usuarioBD['nombre'];
                $respuesta['email'] = $usuarioBD['email'];
                $respuesta['api_token'] = $usuarioBD['api_token'];
                return ["estado" => self::ESTADO_EXITO, "usuario" => $respuesta];
            } else {
                throw new ExcepcionApi(self::ESTADO_FALLA_DESCONOCIDO, "Ha ocurrido un error");
            }
        } else {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "Credenciales incorrectas", 401);
        }
    }

    private static function autenticarUsuario($email, $password)
    {
        $comando = "SELECT password FROM " . self::NOMBRE_TABLA . " WHERE " . self::EMAIL . "=?";

        try {
            $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $sentencia->bindParam(1, $email);
            $sentencia->execute();

            if ($sentencia) {
                $resultado = $sentencia->fetch(PDO::FETCH_ASSOC);

                // CORRECCIÓN AQUÍ:
                // Verificamos si $resultado no es false ANTES de leer el password
                if ($resultado && self::validarPassword($password, $resultado['password'])) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } catch (PDOException $e) {
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }

    private static function validarPassword($password, $passwordHash)
    {
        return password_verify($password, $passwordHash);
    }

    private static function obtenerUsuarioPorEmail($email)
    {
        $comando = "SELECT " .
            self::ID . ", " .
            self::NOMBRE . ", " .
            self::EMAIL . ", " .
            self::API_TOKEN .
            " FROM " . self::NOMBRE_TABLA .
            " WHERE " . self::EMAIL . "=?";

        $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
        $sentencia->bindParam(1, $email);

        if ($sentencia->execute()) {
            return $sentencia->fetch(PDO::FETCH_ASSOC);
        } else {
            return null;
        }
    }

    public static function autorizar()
    {
        $cabeceras = apache_request_headers();

        if (isset($cabeceras['Authorization'])) {
            $api_token = $cabeceras['Authorization'];

            //Si viene en formato "Berear <token>", extraer solo el token
            if (stripos($api_token, 'Bearer') === 0) {
                $api_token = trim(substr($api_token, 7));
            }

            if (Usuario::validarApiToken($api_token)) {
                return Usuario::obtenerIdUsuario($api_token);
            } else {
                throw new ExcepcionApi(self::ESTADO_CLAVE_NO_AUTORIZADA, "Clave Api no Autorizada", 401);
            }
        } else {
            throw new ExcepcionApi(self::ESTADO_AUSENCIA_CLAVE_API, "Falta la clave Api", 401);
        }
    }

    private static function validarApiToken($api_token)
    {
        $comando = "SELECT COUNT(" . self::ID . ")" .
            " FROM " . self::NOMBRE_TABLA .
            " WHERE " . self::API_TOKEN . "=?";

        $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
        $sentencia->bindParam(1, $api_token);
        $sentencia->execute();

        return $sentencia->fetchColumn(0) > 0;
    }

    private static function obtenerIdUsuario($api_token)
    {
        $comando = "SELECT " . self::ID .
            " FROM " . self::NOMBRE_TABLA .
            " WHERE " . self::API_TOKEN . "=?";

        $sentencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
        $sentencia->bindParam(1, $api_token);

        if ($sentencia->execute()) {
            $resultado = $sentencia->fetch();
            return $resultado['id'];
        } else {
            return null;
        }
    }

    private static function registroUsuario()
    {
        $cuerpo = file_get_contents('php://input');
        $usuario = json_decode($cuerpo);

        // 1. VALIDACIÓN: Verificamos si el JSON es válido
        if (json_last_error() != JSON_ERROR_NONE || is_null($usuario)) {
            throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "El cuerpo de la petición no es un JSON válido", 400);
        }

        $resultado = self::crearUsuario($usuario);

        switch ($resultado) {
            case self::ESTADO_CREACION_EXITOSA:
                http_response_code(200);
                return [
                    "estado" => self::ESTADO_CREACION_EXITOSA,
                    "mensaje" => "Usuario creado exitosamente"
                ];
            case self::ESTADO_CREACION_FALLIDA:
                throw new ExcepcionApi(self::ESTADO_CREACION_FALLIDA, "Ha ocurrido un error al crear el usuario", 500);
            default:
                throw new ExcepcionApi(self::ESTADO_FALLA_DESCONOCIDO, "Ha ocurrido un error", 500);
        }
    }

    private static function crearUsuario($usuario)
    {
        // 2. VALIDACIÓN: Verificamos que vengan los datos obligatorios
        if (!isset($usuario->nombre) || !isset($usuario->email) || !isset($usuario->password)) {
            throw new ExcepcionApi(
                self::ESTADO_PARAMETROS_INCORRECTOS,
                "Faltan datos obligatorios: nombre, email o password",
                400
            );
        }

        // Ahora es seguro leer las propiedades porque ya sabemos que existen
        $nombre = $usuario->nombre;
        $email = $usuario->email;
        $password = $usuario->password;

        $password_hash = self::encriptarPassword($password);
        $api_token = self::generarApiToken();

        try {
            $comando = "INSERT INTO " . self::NOMBRE_TABLA . " (" .
                self::NOMBRE . ", " .
                self::EMAIL . ", " .
                self::PASSWORD . "," .
                self::API_TOKEN . ")" .
                " VALUES (?, ?, ?, ?)";

            $setencia = ConexionBD::obtenerInstancia()->obtenerBD()->prepare($comando);
            $setencia->bindParam(1, $nombre);
            $setencia->bindParam(2, $email);
            $setencia->bindParam(3, $password_hash);
            $setencia->bindParam(4, $api_token);

            $resultado = $setencia->execute();

            if ($resultado) {
                return self::ESTADO_CREACION_EXITOSA;
            } else {
                return self::ESTADO_CREACION_FALLIDA;
            }
        } catch (PDOException $e) {
            // Manejo específico para duplicados (email repetido)
            // Código 23000 es violación de integridad en SQL
            if ($e->getCode() == 23000) {
                throw new ExcepcionApi(self::ESTADO_PARAMETROS_INCORRECTOS, "El correo electrónico ya está registrado", 400);
            }
            throw new ExcepcionApi(self::ESTADO_ERROR_BD, $e->getMessage(), 500);
        }
    }
    private static function encriptarPassword($passwordPlana)
    {
        if ($passwordPlana)
            return password_hash($passwordPlana, PASSWORD_DEFAULT);
        else
            return null;
    }

    private static function generarApiToken()
    {
        return md5(microtime() . rand());
    }
}
?>