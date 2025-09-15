<?php
require_once '_db.php';
$id = (int)($_GET['id'] ?? 0);
if ($id>0){
  $stmt=$mysqli->prepare("DELETE FROM cheques WHERE id=?");
  $stmt->bind_param('i',$id);
  $stmt->execute();
}
header("Location: index.php");
