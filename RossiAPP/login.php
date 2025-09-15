<?php
require_once "config.php";
session_start();

if (isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit;
}

$error = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'];
    $contraseña = $_POST['contraseña'];

    $sql = "SELECT * FROM usuarios WHERE usuario = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario_db = $result->fetch_assoc();

    // -- Cambiá esta línea! --
    // if ($usuario_db && password_verify($contraseña, $usuario_db['password'])) {
    if ($usuario_db && $contraseña === $usuario_db['password']) {
        $_SESSION['usuario'] = $usuario;
        $_SESSION['rol'] = $usuario_db['rol'];
        header("Location: index.php");
        exit;
    } else {
        $error = "Usuario o contraseña incorrectos.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <meta charset="UTF-8">
    <title>Iniciar sesión - RossiApp</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background: #f6f8fa; }
        .login-box {
            max-width: 340px;
            margin: 90px auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 14px #0002;
            padding: 32px 25px;
            text-align: center;
        }
        .login-box input[type="text"], .login-box input[type="password"] {
            width: 93%;
            padding: 10px;
            margin: 8px 0 18px 0;
            border-radius: 5px;
            border: 1px solid #bbb;
        }
        .login-box button {
            width: 100%;
            padding: 10px;
            background: #244bbd;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 17px;
        }
        .login-box button:hover {
            background: #162c7a;
        }
        .login-box .error { color: #c00; margin-bottom: 12px;}
    </style>
</head>
<body>
<div class="login-box">
    <h2>Iniciar sesión</h2>
    <?php if ($error): ?>
        <div class="error"><?=htmlspecialchars($error)?></div>
    <?php endif; ?>
    <form method="post">
        <input type="text" name="usuario" placeholder="Usuario" required><br>
        <input type="password" name="contraseña" placeholder="Contraseña" required><br>
        <button type="submit">Entrar</button>
    </form>
</div>
</body>
</html>
