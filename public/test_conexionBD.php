<?php
// public/test_conexionBD.php

// 1. Incluimos tu clase de conexión
require_once '../config/conexionBD.php';

echo "<h1>Prueba de Conexión a Base de Datos</h1>";
echo "<hr>";

try {
    // 2. Intentamos obtener la instancia (esto dispara el constructor y la conexión)
    $conexion = ConexionBD::obtenerInstancia()->obtenerBD();

    if ($conexion) {
        echo "<h3 style='color: green;'>✅ ¡Conexión Exitosa!</h3>";
        echo "<p>PHP se ha conectado correctamente a la base de datos: <strong>" . BASE_DE_DATOS . "</strong></p>";

        // 3. Prueba de fuego: Hacemos una consulta real
        // Vamos a pedir la versión de MySQL para asegurar que hay tráfico de datos
        $sentencia = $conexion->query("SELECT VERSION() as version");
        $resultado = $sentencia->fetch(PDO::FETCH_ASSOC);

        echo "<p>Versión de MySQL detectada: " . $resultado['version'] . "</p>";
        echo "<p>Cotejamiento (Charset): utf8mb4 (Configurado correctamente)</p>";
    }

} catch (Exception $e) {
    // Si algo falla, aquí te dirá exactamente qué pasó (contraseña mal, host incorrecto, etc.)
    echo "<h3 style='color: red;'>❌ Error de Conexión</h3>";
    echo "<p>Detalle del error: " . $e->getMessage() . "</p>";

    echo "<hr>";
    echo "<h4>Posibles causas:</h4>";
    echo "<ul>";
    echo "<li>El usuario 'root' tiene contraseña en tu XAMPP? (Revisa login_mysql.php)</li>";
    echo "<li>El nombre de la base de datos 'sistema_gestion_tareas' está bien escrito?</li>";
    echo "<li>XAMPP (MySQL) está prendido?</li>";
    echo "</ul>";
}
?>