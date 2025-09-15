<?php
require_once '_auth.php';
require_once '_db.php';

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$estado = isset($_POST['estado']) ? $_POST['estado'] : '';

$permitidos = ['por_entrar', 'pagado'];

if ($id > 0 && in_array($estado, $permitidos, true)) {
    $stmt = $mysqli->prepare("UPDATE cheques SET estado = ? WHERE id = ?");
    $stmt->bind_param('si', $estado, $id);
    $stmt->execute();
}

header("Location: index.php");
exit;
