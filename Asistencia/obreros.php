<?php
// CONFIG MYSQL (misma que index.php)
$db_host = 'localhost';
$db_user = 'empresa';
$db_pass = 'v3t3r4n0';
$db_name = 'asistencia';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) die("Error DB: " . $conn->connect_error);

// LISTADO DE OBRAS
$obras = [];
$res = $conn->query("SELECT * FROM obras ORDER BY nombre");
while ($row = $res->fetch_assoc()) $obras[] = $row;

// AGREGAR / EDITAR OBRERO
if (isset($_POST['guardar'])) {
    $nombre = trim($_POST['nombre']);
    $obra_id = intval($_POST['obra_id']);
    $id = intval($_POST['id'] ?? 0);

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE obreros SET nombre=?, obra_id=? WHERE id=?");
        $stmt->bind_param("sii", $nombre, $obra_id, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO obreros (nombre, obra_id) VALUES (?, ?)");
        $stmt->bind_param("si", $nombre, $obra_id);
    }
    $stmt->execute();
    $stmt->close();
    header("Location: obreros.php");
    exit;
}

// BORRAR
if (isset($_GET['borrar'])) {
    $id = intval($_GET['borrar']);
    $conn->query("DELETE FROM obreros WHERE id=$id");
    header("Location: obreros.php");
    exit;
}

// LISTADO DE OBREROS
$obreros = $conn->query("SELECT o.id, o.nombre, ob.nombre AS obra
                         FROM obreros o
                         LEFT JOIN obras ob ON o.obra_id = ob.id
                         ORDER BY ob.nombre, o.nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Administrar Obreros</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <h2 class="mb-4">Administrar Obreros</h2>
    <form method="POST" class="row g-2 mb-4">
        <input type="hidden" name="id" value="">
        <div class="col-md-5">
            <input type="text" name="nombre" class="form-control" placeholder="Nombre del obrero" required>
        </div>
        <div class="col-md-4">
            <select name="obra_id" class="form-select" required>
                <option value="">-- Seleccionar Obra --</option>
                <?php foreach($obras as $ob): ?>
                    <option value="<?=$ob['id']?>"><?=htmlspecialchars($ob['nombre'])?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-success w-100" name="guardar">Agregar Obrero</button>
        </div>
    </form>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Nombre</th>
                <th>Obra</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while($o = $obreros->fetch_assoc()): ?>
            <tr>
                <td><?=htmlspecialchars($o['nombre'])?></td>
                <td><?=htmlspecialchars($o['obra'])?></td>
                <td>
                    <a href="obreros.php?borrar=<?=$o['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Borrar este obrero?')">Borrar</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <a href="index.php" class="btn btn-secondary mt-3">← Volver a Asistencia</a>
</div>
</body>
</html>
