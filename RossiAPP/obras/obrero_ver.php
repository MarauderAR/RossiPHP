<?php
require_once("../config.php");

// Proceso edición de obrero
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_obrero'])) {
    $id = intval($_POST['edit_obrero']);
    $nombre = trim($_POST['nombre']);
    $cuil = trim($_POST['cuil']);
    $categoria = trim($_POST['categoria']);
    $telefono = trim($_POST['telefono']);
    $sueldo_por_dia = floatval($_POST['sueldo_por_dia']);
    $stmt = $conn->prepare("UPDATE obreros SET nombre=?, cuil=?, categoria=?, telefono=?, sueldo_por_dia=? WHERE id=?");
    $stmt->bind_param("ssssdi", $nombre, $cuil, $categoria, $telefono, $sueldo_por_dia, $id);
    $stmt->execute();
    header("Location: obrero_ver.php?editado=1");
    exit;
}

// Eliminar obrero y sus datos asociados si corresponde
if (isset($_GET['borrar']) && is_numeric($_GET['borrar'])) {
    $obrero_id = intval($_GET['borrar']);
    $conn->query("DELETE FROM pagos_semanales WHERE obrero_id = $obrero_id");
    $conn->query("DELETE FROM obra_obrero WHERE obrero_id = $obrero_id");
    $conn->query("DELETE FROM obreros WHERE id = $obrero_id");
    header("Location: obrero_ver.php");
    exit;
}

// Obtener lista de obreros
$res = $conn->query("SELECT * FROM obreros ORDER BY nombre");

// Si hay edición, traer datos del obrero
$edit_data = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_data = $conn->query("SELECT * FROM obreros WHERE id=$edit_id")->fetch_assoc();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Gestión de Obreros</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .btn-accion { margin-right: 6px; }
        .table > :not(:first-child) { border-top: 2px solid #cbd5e1;}
        .modal-backdrop { z-index: 1040 !important; }
        .modal-dialog { z-index: 1100 !important; }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <h2 class="mb-4 text-center">Listado General de Obreros</h2>
    <?php if (isset($_GET['editado'])): ?>
        <div class="alert alert-success">Obrero actualizado correctamente.</div>
    <?php endif; ?>
    <div class="mb-3">
        <a href="obrero_nuevo.php" class="btn btn-success btn-sm btn-accion">+ Agregar Obrero</a>
        <a href="index.php" class="btn btn-secondary btn-sm btn-accion">← Volver</a>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-primary">
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>CUIL</th>
                    <th>Categoría</th>
                    <th>Teléfono</th>
                    <th>Sueldo x Día</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if($res->num_rows > 0): ?>
                <?php while($o = $res->fetch_assoc()): ?>
                <tr>
                    <td><?=$o['id']?></td>
                    <td><?=htmlspecialchars($o['nombre'] ?? '')?></td>
                    <td><?=htmlspecialchars($o['cuil'] ?? '')?></td>
                    <td><?=htmlspecialchars($o['categoria'] ?? '')?></td>
                    <td><?=htmlspecialchars($o['telefono'] ?? '')?></td>
                    <td><?=number_format($o['sueldo_por_dia'], 2, ',', '.')?></td>
                    <td>
                        <a href="obrero_ver.php?edit=<?=$o['id']?>" class="btn btn-primary btn-sm btn-accion">Editar</a>
                        <a href="obrero_ver.php?borrar=<?=$o['id']?>" class="btn btn-danger btn-sm btn-accion" onclick="return confirm('¿Seguro de borrar el obrero, todos sus pagos y asignaciones?')">Borrar</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" class="text-center">No hay obreros registrados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if ($edit_data): ?>
<!-- Modal de edición -->
<div class="modal fade show" tabindex="-1" style="display:block; background:#2227a388;" id="modalEditObrero">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Obrero</h5>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="edit_obrero" value="<?=$edit_data['id']?>">
                    <div class="mb-2">
                        <label>Nombre:</label>
                        <input type="text" name="nombre" class="form-control" value="<?=htmlspecialchars($edit_data['nombre'])?>" required>
                    </div>
                    <div class="mb-2">
                        <label>CUIL:</label>
                        <input type="text" name="cuil" class="form-control" value="<?=htmlspecialchars($edit_data['cuil'])?>">
                    </div>
                    <div class="mb-2">
                        <label>Categoría:</label>
                        <input type="text" name="categoria" class="form-control" value="<?=htmlspecialchars($edit_data['categoria'])?>">
                    </div>
                    <div class="mb-2">
                        <label>Teléfono:</label>
                        <input type="text" name="telefono" class="form-control" value="<?=htmlspecialchars($edit_data['telefono'])?>">
                    </div>
                    <div class="mb-2">
                        <label>Sueldo por Día:</label>
                        <input type="number" step="0.01" name="sueldo_por_dia" class="form-control" value="<?=htmlspecialchars($edit_data['sueldo_por_dia'])?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Guardar cambios</button>
                    <a href="obrero_ver.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    // Para evitar que el usuario siga en modal si recarga
    document.body.classList.add('modal-open');
    setTimeout(()=>window.scrollTo(0,0),80);
</script>
<?php endif; ?>
</body>
</html>
