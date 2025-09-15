<?php
header('Content-Type: application/json');

// Conexión a la base de datos
require_once("../config.php");

$usuario = $_POST['usuario'] ?? '';
$password = $_POST['password'] ?? '';

if (!$usuario || !$password) {
    echo json_encode(["status" => "error", "mensaje" => "Faltan datos"]);
    exit;
}

$stmt = $conn->prepare("SELECT id, usuario, rol FROM usuarios WHERE usuario = ? AND password = ? LIMIT 1");
$stmt->bind_param("ss", $usuario, $password);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        "status" => "ok",
        "id" => $row['id'],
        "usuario" => $row['usuario'],
        "rol" => $row['rol']
    ]);
} else {
    echo json_encode(["status" => "error", "mensaje" => "Usuario o contraseña incorrectos"]);
}

$stmt->close();
$conn->close();
