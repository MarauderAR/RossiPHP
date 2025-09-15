<?php
require_once("../config.php");

// Traer todas las obras
$obras = $conn->query("SELECT id, nombre FROM obras ORDER BY nombre");

// Determinar obra seleccionada
$obra_id = isset($_POST['obra_id']) ? intval($_POST['obra_id']) : (isset($_GET['obra_id']) ? intval($_GET['obra_id']) : 0);

$mensaje = "";

// Procesar alta individual de obrero
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nombre']) && $obra_id) {
    $nombre = trim($_POST['nombre']);
    $sueldo_por_dia = floatval($_POST['sueldo_por_dia']);
    // 1. Insertar obrero a tabla obreros
    $stmt = $conn->prepare("INSERT INTO obreros (nombre, sueldo_por_dia) VALUES (?, ?)");
    $stmt->bind_param("sd", $nombre, $sueldo_por_dia);
    $stmt->execute();
    $obrero_id = $conn->insert_id; // Obtener el ID recién creado

    // 2. Insertar vínculo en obra_obrero
    $stmt2 = $conn->prepare("INSERT INTO obra_obrero (obra_id, obrero_id) VALUES (?, ?)");
    $stmt2->bind_param("ii", $obra_id, $obrero_id);
    $stmt2->execute();

    $mensaje = "<span style='color:green;'>Obrero agregado correctamente.</span>";
}

// Si hay una obra seleccionada, traigo sus obreros desde la relación obra_obrero
$obreros = [];
if ($obra_id) {
    $obreros = $conn->query(
        "SELECT o.nombre FROM obreros o
        JOIN obra_obrero oo ON oo.obrero_id = o.id
        WHERE oo.obra_id = $obra_id
        ORDER BY o.nombre"
    );
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <title>Agregar Obreros</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .input-corto { width: 320px; }
        .obrero-lista { margin-top: 25px; }
        .obrero-lista ul { padding-left: 18px; }
        .boton-volver { margin-top: 20px; display: inline-block; }
        textarea { width: 350px; height: 70px; }
    </style>
</head>
<body>
    <h2>Agregar Obreros</h2>
    <form method="get" action="">
        <label>Obra:
            <select name="obra_id" onchange="this.form.submit()">
                <option value="">-- Seleccionar --</option>
                <?php foreach ($obras as $o): ?>
                    <option value="<?=$o['id']?>" <?=$obra_id == $o['id'] ? 'selected' : ''?>><?=htmlspecialchars($o['nombre'])?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
    <br>
    <?php if ($obra_id): ?>
        <form method="post" action="">
            <input type="hidden" name="obra_id" value="<?=$obra_id?>">
            <label>Nombre de obrero:<br>
                <input type="text" name="nombre" class="input-corto" placeholder="Nombre completo" required>
            </label>
            <br>
            <label>Sueldo por Día:<br>
                <input type="number" step="0.01" name="sueldo_por_dia" class="input-corto" required>
            </label>
            <br>
            <input type="submit" value="Agregar">
        </form>
        <?=$mensaje?>
        <div class="obrero-lista">
            <h3>Obreros en esta obra:</h3>
            <ul>
                <?php foreach ($obreros as $o): ?>
                    <li><?=htmlspecialchars($o['nombre'])?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php else: ?>
        <p>Seleccione primero la obra a la que desea agregar obreros.</p>
    <?php endif; ?>
    <a href="index.php" class="boton-volver">Volver</a>
</body>
</html>
