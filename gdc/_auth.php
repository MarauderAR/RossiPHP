<?php
// _auth.php  — protege el panel usando la tabla rossi_db.usuarios
@session_start();
require_once __DIR__.'/_db.php'; // tenemos host/usuario/clave acá

// === CONFIGURABLE ===
define('AUTH_DB', 'rossi_db');      // DB donde está la tabla usuarios
define('AUTH_TABLE', 'usuarios');   // nombre de la tabla
$ALLOWED_ROLES = ['admin'];         // roles habilitados para entrar al panel
$LOGIN_PAGE = 'login_gdc.php';      // login local que valida contra rossi_db

// 1) ¿Ya hay sesión válida de este mini-módulo?
if (!empty($_SESSION['gdc_user'])) {
    return; // OK
}

// 2) (Opcional) ¿Existe sesión global de tu app?
// Si tu app guarda estas variables, descomentá y ajustá nombres:
// if (!empty($_SESSION['user_id']) && !empty($_SESSION['rol'])) {
//     if (in_array(strtolower($_SESSION['rol']), array_map('strtolower',$ALLOWED_ROLES))) {
//         $_SESSION['gdc_user'] = [
//           'id' => (int)$_SESSION['user_id'],
//           'usuario' => $_SESSION['usuario'] ?? 'user',
//           'rol' => $_SESSION['rol'],
//         ];
//         return;
//     }
// }

// 3) Si no hay sesión válida, redirigimos al login local de GDC
header('Location: '.$LOGIN_PAGE);
exit;
