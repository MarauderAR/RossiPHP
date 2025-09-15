<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}
$nombre = htmlspecialchars($_SESSION['usuario']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Panel Principal - RossiApp</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f6f8fa; text-align: center; }
        .container { max-width: 480px; margin: 60px auto; background: #fff; border-radius: 7px; box-shadow: 0 2px 10px #0002; padding: 35px 25px; }
        h2 { margin-bottom: 30px; }
        .btn { display: block; margin: 15px auto; padding: 12px 0; width: 80%; background: #244bbd; color: #fff; border: none; border-radius: 5px; font-size: 18px; text-decoration: none; transition: 0.1s; }
        .btn:hover { background: #182c7a; }
        .logout { margin-top: 30px; color: #555; }
    </style>
</head>
<body>
<div class="container">
    <h2>Bienvenido, <?=$nombre?></h2>
    <a href="obras/" class="btn">Control de Obras</a>
    <a href="gestion/" class="btn">Gestión de Facturas</a>
    <div class="logout">
        <a href="logout.php">Cerrar Sesión</a>
    </div>
</div>
</body>
</html>
