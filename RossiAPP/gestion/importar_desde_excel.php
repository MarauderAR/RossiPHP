<?php
require_once("../config.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $proveedor_id = intval($_POST['proveedor_id']);
    $datos = trim($_POST['facturas']);
    $filas = explode("\n", $datos);
    $importadas = 0;
    $errores = [];

    foreach ($filas as $i => $fila) {
        $fila = trim($fila);
        if ($fila == "") continue;

        // Ahora se separa por TAB
        $partes = preg_split("/\t+/", $fila);
        if (count($partes) < 5) {
            $errores[] = "Fila ".($i+1).": columnas insuficientes (".count($partes).")";
            continue;
        }

        // Fecha
        $fecha = date('Y-m-d', strtotime(str_replace('/', '-', trim($partes[0]))));
        // Comprobante (tipo y numero juntos)
        $numero = trim($partes[1]);
        // Debe
        $debe = floatval(str_replace(['.', ','], ['', '.'], trim($partes[2])));
        // Haber
        $haber = floatval(str_replace(['.', ','], ['', '.'], trim($partes[3])));
        // Saldo Excel
        $saldo_excel = floatval(str_replace(['.', ','], ['', '.'], trim($partes[4])));

        // Insert
        $stmt = $conn->prepare("INSERT INTO facturas (proveedor_id, fecha, numero, debe, haber, saldo_excel) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issddd", $proveedor_id, $fecha, $numero, $debe, $haber, $saldo_excel);
        if ($stmt->execute()) {
            $importadas++;
        } else {
            $errores[] = "Fila ".($i+1).": error al insertar";
        }
    }

    echo "<h3>Importadas: $importadas facturas</h3>";
    if (!empty($errores)) {
        echo "<b>Errores:</b><br><ul>";
        foreach($errores as $err) echo "<li>$err</li>";
        echo "</ul>";
    }
    echo "<a href='index.php'>Volver</a>";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<title>Importar Facturas desde Excel</title>
</head>
<body>
    <h2>Importar Facturas por Copiar y Pegar</h2>
    <form method="post">
        ID Proveedor: <input type="number" name="proveedor_id" required><br>
        <p>Pegá directamente desde el Excel (las 5 columnas, separadas por TAB):<br>
        Fecha <b>TAB</b> Comprobante <b>TAB</b> Debe <b>TAB</b> Haber <b>TAB</b> Saldo</p>
        <textarea name="facturas" rows="25" cols="80" placeholder="26/12/24<TAB>FAA3-10-25586<TAB>100000000<TAB>0<TAB>100000000"></textarea><br>
        <input type="submit" value="Importar">
        <a href="index.php" style="margin-left:20px; color:#222; text-decoration:none;">Volver</a>
    </form>
    <p><b>Ejemplo:</b><br>
    26/12/24 [TAB] FAA3-10-25586 [TAB] 100000000 [TAB] 0 [TAB] 100000000</p>
</body>
</html>
