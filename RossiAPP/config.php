<?php
$host = "localhost";
$user = "empresa";
$pass = "v3t3r4n0"; // tu pass, si tenés
$db   = "rossi_db";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>
