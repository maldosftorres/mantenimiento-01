<?php
// db_config.php

// Configuración de la conexión a la base de datos.
$host = 'localhost';
$db    = 'mantenimiento_db'; // <--- ¡¡¡ESTA LÍNEA DEBE SER EXACTAMENTE ASÍ!!!
$user = 'root';
$pass = ''; // Contraseña VACÍA (dos comillas simples sin nada dentro)

$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log("ERROR DE CONEXIÓN A LA BASE DE DATOS: " . $e->getMessage());
    die("<h3>ERROR GRAVE DE CONEXIÓN A LA BASE DE DATOS.</h3><p>Por favor, contacte al administrador del sistema.</p>");
}
?>