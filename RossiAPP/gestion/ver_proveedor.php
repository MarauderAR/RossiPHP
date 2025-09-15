<?php
require_once("../config.php");

// Obtener el ID del proveedor desde la URL
$proveedor_id = intval($_GET['id'] ?? 0);
if ($proveedor_id <= 0) {
    die("Proveedor inválido");
}

// ---------- ELIMINAR FACTURA ----------
if (isset($_GET['borrar_factura'])) {
    $factura_id = intval($_GET['borrar_factura']);

    // Leer archivo adjunto (si existe) con prepared
    $stmtDel = $conn->prepare("SELECT archivo FROM facturas WHERE id = ? AND proveedor_id = ?");
    $stmtDel->bind_param("ii", $factura_id, $proveedor_id);
    $stmtDel->execute();
    $stmtDel->bind_result($archivoDb);
    $tiene = $stmtDel->fetch();
    $stmtDel->close();

    if ($tiene) {
        // Normalizar ruta: puede venir como "uploads/facturas/...", "uploads/...", o sólo nombre
        $rel = trim((string)$archivoDb);
        if ($rel !== '') {
            if (strpos($rel, 'uploads/') !== 0) {
                // si es sólo nombre, asumimos carpeta uploads/ (legacy)
                $rel = 'uploads/' . $rel;
            }
            // Ruta absoluta en disco para borrar
            $abs = realpath(__DIR__ . '/' . $rel);
            // Por seguridad, sólo borrar si está dentro de la carpeta de proyecto
            $base = realpath(__DIR__);
            if ($abs && strpos($abs, $base) === 0 && file_exists($abs)) {
                @unlink($abs);
            }
        }

        // Borrar factura
        $stmtDel2 = $conn->prepare("DELETE FROM facturas WHERE id = ? AND proveedor_id = ?");
        $stmtDel2->bind_param("ii", $factura_id, $proveedor_id);
        $stmtDel2->execute();
        $stmtDel2->close();
    }

    header("Location: ver_proveedor.php?id=$proveedor_id"); // recarga limpia
    exit;
}

// ---------- DATOS DEL PROVEEDOR ----------
$stmt = $conn->prepare("SELECT * FROM proveedores WHERE id = ?");
$stmt->bind_param("i", $proveedor_id);
$stmt->execute();
$proveedor = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$proveedor) {
    die("Proveedor no encontrado");
}

// ---------- LISTADO DE FACTURAS DEL PROVEEDOR (con Obra) ----------
$stmtFact = $conn->prepare("
    SELECT f.*, o.nombre AS obra_nombre
    FROM facturas f
    LEFT JOIN obras o ON o.id = f.obra_id
    WHERE f.proveedor_id = ?
    ORDER BY f.fecha DESC, f.id DESC
");
$stmtFact->bind_param("i", $proveedor_id);
$stmtFact->execute();
$facturas = $stmtFact->get_result();

// --------- NUEVO: GESTIÓN DE RESUMEN DE CUENTA ---------
// AGREGAR MOVIMIENTO
if (isset($_POST['agregar_resumen'])) {
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $importe = (float)($_POST['importe'] ?? 0);
    $adjunto = null;

    // Adjuntar archivo (opcional)
    if (!empty($_FILES['adjunto']['name']) && is_uploaded_file($_FILES['adjunto']['tmp_name'])) {
        $target_dir_fs = __DIR__ . "/uploads/proveedor_cc";
        if (!is_dir($target_dir_fs)) @mkdir($target_dir_fs, 0777, true);
        $safe = preg_replace('/[^A-Za-z0-9_\.-]/', '_', basename($_FILES["adjunto"]["name"]));
        $filename = date('YmdHis') . '_' . $safe;
        $target_file_fs = $target_dir_fs . '/' . $filename;
        if (move_uploaded_file($_FILES["adjunto"]["tmp_name"], $target_file_fs)) {
            // Guardamos ruta relativa para la web
            $adjunto = "uploads/proveedor_cc/" . $filename;
        }
    }

    $stmt_cc = $conn->prepare("
        INSERT INTO proveedor_resumen_cuenta (proveedor_id, fecha, descripcion, importe, adjunto)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt_cc->bind_param("issds", $proveedor_id, $fecha, $descripcion, $importe, $adjunto);
    $stmt_cc->execute();
    $stmt_cc->close();

    header("Location: ver_proveedor.php?id=$proveedor_id&tab=cc");
    exit;
}

// ELIMINAR MOVIMIENTO CC
if (isset($_GET['borrar_cc'])) {
    $cc_id = intval($_GET['borrar_cc']);

    $stmtGet = $conn->prepare("SELECT adjunto FROM proveedor_resumen_cuenta WHERE id = ? AND proveedor_id = ?");
    $stmtGet->bind_param("ii", $cc_id, $proveedor_id);
    $stmtGet->execute();
    $stmtGet->bind_result($adj);
    $ok = $stmtGet->fetch();
    $stmtGet->close();

    if ($ok && $adj) {
        $abs = realpath(__DIR__ . '/' . $adj);
        $base = realpath(__DIR__);
        if ($abs && strpos($abs, $base) === 0 && file_exists($abs)) {
            @unlink($abs);
        }
    }

    $stmtDel = $conn->prepare("DELETE FROM proveedor_resumen_cuenta WHERE id = ? AND proveedor_id = ?");
    $stmtDel->bind_param("ii", $cc_id, $proveedor_id);
    $stmtDel->execute();
    $stmtDel->close();

    header("Location: ver_proveedor.php?id=$proveedor_id&tab=cc");
    exit;
}

// LISTADO CC
$cc = $conn->query("SELECT * FROM proveedor_resumen_cuenta WHERE proveedor_id = $proveedor_id ORDER BY fecha DESC, id DESC");
$total_cc = 0;
while($rw = $cc->fetch_assoc()) { $total_cc += (float)$rw['importe']; }
$cc->data_seek(0); // volver al inicio para renderizar

// Tab activa
$tab = $_GET['tab'] ?? 'facturas';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Facturas de <?=htmlspecialchars($proveedor['nombre'])?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
    body{background:#f6f8fa;}
    .saldo-cc { font-weight: bold; font-size: 1.1em;}
    @media (max-width: 576px) {
      .table-responsive { font-size: 0.93em; }
      .nav-tabs .nav-link { font-size: 1em; }
    }
</style>
</head>
<body>
<div class="container py-4">
    <h2 class="mb-3">Proveedor: <?=htmlspecialchars($proveedor['nombre'])?></h2>
    <div class="mb-3">
        <strong>Teléfono:</strong> <?=htmlspecialchars($proveedor['telefono'])?> <br>
        <strong>Dirección:</strong> <?=htmlspecialchars($proveedor['direccion'])?>
    </div>

    <div class="mb-3">
        <a href="index.php" class="btn btn-link">← Volver a proveedores</a>
        <a href="factura_nueva.php?id=<?=$proveedor_id?>" class="btn btn-success ms-2">+ Nueva Factura</a>
    </div>

    <!-- Nav tabs -->
    <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link <?=$tab=='facturas'?'active':''?>" id="facturas-tab" data-bs-toggle="tab" data-bs-target="#facturas" type="button" role="tab">Facturas</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link <?=$tab=='cc'?'active':''?>" id="cc-tab" data-bs-toggle="tab" data-bs-target="#cc" type="button" role="tab">Resumen de cuenta</button>
      </li>
    </ul>

    <div class="tab-content">
      <!-- TAB FACTURAS -->
      <div class="tab-pane fade <?=$tab=='facturas'?'show active':''?>" id="facturas" role="tabpanel">
        <div class="table-responsive">
          <table class="table table-bordered table-striped align-middle">
            <thead class="table-primary">
              <tr>
                <th>Número</th>
                <th>Fecha</th>
                <th>Obra</th>
                <th class="text-end">Monto</th>
                <th class="text-end">Debe</th>
                <th class="text-end">Haber</th>
                <th class="text-end">Saldo</th>
                <th>Archivo</th>
                <th>Acción</th>
              </tr>
            </thead>
            <tbody>
            <?php 
              $saldo_total = 0;
              while ($factura = $facturas->fetch_assoc()):
                  $debe  = (float)$factura['debe'];
                  $haber = (float)$factura['haber'];
                  $saldo_factura = $debe - $haber;
                  $saldo_total += $saldo_factura;

                  // Armar href del adjunto
                  $archivo = trim((string)$factura['archivo']);
                  $href = null;
                  if ($archivo !== '') {
                      $href = (strpos($archivo, 'uploads/') === 0) ? $archivo : ('uploads/' . $archivo);
                  }
            ?>
              <tr>
                <td><?= htmlspecialchars($factura['numero']) ?></td>
                <td><?= htmlspecialchars($factura['fecha']) ?></td>
                <td><?= htmlspecialchars($factura['obra_nombre'] ?? '—') ?></td>
                <td class="text-end"><?= number_format((float)$factura['monto_total'], 2, ',', '.') ?></td>
                <td class="text-end"><?= number_format($debe, 2, ',', '.') ?></td>
                <td class="text-end"><?= number_format($haber, 2, ',', '.') ?></td>
                <td class="text-end"><?= number_format($saldo_factura, 2, ',', '.') ?></td>
                <td>
                  <?php if ($href): ?>
                    <a href="<?= htmlspecialchars($href) ?>" target="_blank" class="btn btn-outline-info btn-sm">Ver adjunto</a>
                  <?php else: ?>
                    <span class="text-muted">Sin archivo</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!$href): ?>
                    <a href="adjuntar_archivo.php?id=<?=intval($factura['id'])?>" class="btn btn-outline-secondary btn-sm">Adjuntar PDF</a>
                  <?php else: ?>
                    <span style="color:#47a647;">✔</span>
                  <?php endif; ?>
                  <a href="ver_proveedor.php?id=<?=$proveedor_id?>&borrar_factura=<?=intval($factura['id'])?>"
                     onclick="return confirm('¿Seguro que querés eliminar esta factura?');"
                     class="btn btn-outline-danger btn-sm ms-1">Eliminar</a>
                </td>
              </tr>
            <?php endwhile; ?>
              <tr class="fw-bold" style="background:#f0f0f0;">
                <td colspan="6" class="text-end">SALDO TOTAL PROVEEDOR:</td>
                <td class="text-end"><?= number_format($saldo_total, 2, ',', '.') ?></td>
                <td colspan="2"></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB RESUMEN DE CUENTA -->
      <div class="tab-pane fade <?=$tab=='cc'?'show active':''?>" id="cc" role="tabpanel">
        <div class="card mb-4 shadow-sm">
          <div class="card-header">Agregar movimiento al Resumen de cuenta</div>
          <div class="card-body">
            <form method="POST" enctype="multipart/form-data" class="row gy-2 gx-2 align-items-end">
              <div class="col-md-2 col-6">
                <input type="date" class="form-control" name="fecha" value="<?=date('Y-m-d')?>" required>
              </div>
              <div class="col-md-4 col-12">
                <input type="text" class="form-control" name="descripcion" placeholder="Descripción (ej: Resumen Julio, Pago parcial)" required>
              </div>
              <div class="col-md-2 col-6">
                <input type="number" step="0.01" class="form-control" name="importe" placeholder="Importe (+deuda, -pago)" required>
              </div>
              <div class="col-md-3 col-12">
                <input type="file" class="form-control" name="adjunto" accept="image/*,application/pdf">
              </div>
              <div class="col-md-1 col-12">
                <button class="btn btn-primary w-100" name="agregar_resumen" type="submit">Agregar</button>
              </div>
            </form>
          </div>
        </div>
        <div class="card shadow">
          <div class="card-header bg-secondary bg-opacity-10">Historial de movimientos</div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped table-bordered mb-0">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Descripción</th>
                    <th>Importe</th>
                    <th>Adjunto</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                <?php 
                  $total_cc = 0;
                  while($rw = $cc->fetch_assoc()):
                    $total_cc += (float)$rw['importe'];
                ?>
                  <tr>
                    <td><?=htmlspecialchars($rw['fecha'])?></td>
                    <td><?=htmlspecialchars($rw['descripcion'])?></td>
                    <td class="<?=$rw['importe']<0?'text-success':'text-danger'?>">
                      <?=number_format((float)$rw['importe'],2,',','.')?>
                    </td>
                    <td>
                      <?php if($rw['adjunto']): ?>
                        <a href="<?=htmlspecialchars($rw['adjunto'])?>" target="_blank">Ver archivo</a>
                      <?php else: ?>
                        —
                      <?php endif ?>
                    </td>
                    <td>
                      <a href="?id=<?=$proveedor_id?>&tab=cc&borrar_cc=<?=intval($rw['id'])?>"
                         onclick="return confirm('¿Seguro que querés borrar este movimiento?')"
                         class="btn btn-sm btn-outline-danger">Borrar</a>
                    </td>
                  </tr>
                <?php endwhile ?>
                </tbody>
                <tfoot>
                  <tr>
                    <th colspan="2">Saldo actual</th>
                    <th colspan="3" class="saldo-cc <?=($total_cc<0)?'text-success':'text-danger'?>">
                      <?=number_format($total_cc,2,',','.')?>
                    </th>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>
      </div> <!-- tab cc -->
    </div> <!-- tab content -->

    <a href="index.php" class="btn btn-link mt-3">← Volver a proveedores</a>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Mantener tab activo tras reload/redirigir con &tab=
  let url = new URL(window.location.href);
  if (url.searchParams.get('tab')) {
    let activeTab = url.searchParams.get('tab');
    let tabBtn = document.querySelector(`button[data-bs-target="#${activeTab}"]`);
    if (tabBtn) new bootstrap.Tab(tabBtn).show();
  }
</script>
</body>
</html>
