<?php
@session_start();
require_once __DIR__.'/_db.php'; // credenciales
require_once __DIR__.'/config.php'; // helpers h()

// Conexión a la DB donde está la tabla usuarios
$authdb = new mysqli(DB_HOST, DB_USER, DB_PASS, 'rossi_db');
if ($authdb->connect_errno) { die("Auth DB error: ".$authdb->connect_error); }
$authdb->set_charset('utf8mb4');

$error = null;

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $user = trim($_POST['usuario'] ?? '');
    $pass = $_POST['password'] ?? '';

    $stmt = $authdb->prepare("SELECT id, usuario, password, rol FROM usuarios WHERE usuario=? LIMIT 1");
    $stmt->bind_param('s', $user);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();

    if ($u) {
        $ok = false;
        // Soporta texto plano (como en tu captura) y también hash si en el futuro lo cambian
        if ($u['password'] === $pass) {
            $ok = true;
        } elseif (password_verify($pass, $u['password'])) {
            $ok = true;
        }
        if ($ok && in_array(strtolower($u['rol']), ['admin'])) {
            $_SESSION['gdc_user'] = [
              'id' => (int)$u['id'],
              'usuario' => $u['usuario'],
              'rol' => $u['rol'],
            ];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Credenciales inválidas o rol sin permiso.';
        }
    } else {
        $error = 'Usuario no encontrado.';
    }
}

include '_layout_head.php';
?>
<div class="row justify-content-center">
  <div class="col-12 col-sm-8 col-md-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="card-title mb-3">Ingreso al panel de Cheques</h5>
        <?php if($error): ?><div class="alert alert-danger"><?=h($error)?></div><?php endif; ?>
        <form method="post" class="row g-3">
          <div class="col-12">
            <label class="form-label">Usuario</label>
            <input name="usuario" class="form-control" required autofocus>
          </div>
          <div class="col-12">
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <div class="col-12 d-grid">
            <button class="btn btn-primary">Entrar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include '_layout_foot.php'; ?>
