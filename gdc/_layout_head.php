<?php /* Bootstrap 5 + estilos + navbar con sesión del panel GDC */ ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gestión de Cheques</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
</head>
<body class="bg-light">

<?php @session_start(); ?>
<nav class="navbar navbar-dark bg-dark">
  <div class="container-fluid">
    <span class="navbar-brand">Gestión de Cheques</span>
    <div class="d-print-none">
      <?php if (!empty($_SESSION['gdc_user'])): ?>
        <a href="index.php" class="btn btn-outline-light btn-sm me-2">Panel</a>
        <a href="token_new.php" class="btn btn-warning btn-sm">Crear link solo lectura</a>
        <a href="logout_gdc.php" class="btn btn-outline-light btn-sm ms-2">Salir</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<div class="container my-4">
