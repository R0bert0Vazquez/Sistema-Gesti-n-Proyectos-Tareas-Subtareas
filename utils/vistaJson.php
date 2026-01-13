<?php
require_once "vistaApi.php";

/**
 * Clase para imprimir en la salida respuestas con formato JSON
 */
class VistaJson extends VistaApi
{
    const ESTADO_URL_INCORRECTA = 1;
    const ESTADO_CREACION_EXITOSA = 2;
    const ESTADO_CREACION_FALLIDA = 3;
    const ESTADO_FALLA_DESCONOCIDO = 4;
    const ESTADO_ERROR_BD = 5;
    const ESTADO_PARAMETROS_INCORRECTOS = 7;

    public function __construct($estado = 400)
    {
        $this->estado = $estado;
    }

    /**
     * Imprime el cuerpo de la respuesta y setea el código de respuesta
     * @param mixed $cuerpo de la respuesta a enviar
     */
    public function imprimir($cuerpo)
    {
        if ($this->estado) {
            http_response_code($this->estado);
        }
        //http_response_code(($cuerpo["estado"]==2)?200:403);
        header('Content-Type: application/json; charset=utf8mb4');
        echo json_encode($cuerpo, JSON_PRETTY_PRINT);
        exit;
    }
}
?>