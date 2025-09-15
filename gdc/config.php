<?php
// CONFIGURACIÓN MYSQL (db_gdc)
define('DB_HOST','localhost');
define('DB_USER','empresa');
define('DB_PASS','v3t3r4n0');
define('DB_NAME','db_gdc');

// Zona horaria AR
date_default_timezone_set('America/Argentina/Buenos_Aires');

// ===== Helpers =====
function ars_money($v){
  return number_format((float)$v, 2, ',', '.');
}
function h($s){
  return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
/**
 * Convierte texto de dinero a float.
 * Acepta: "1.234.567,89" | "1234567.89" | "1234567" | "$ 1.234,00"
 */
function parse_money_ar($s){
  if ($s === null) return 0.0;
  $s = trim((string)$s);
  if ($s === '') return 0.0;
  // Quita todo excepto dígitos, puntos, comas y signos menos
  $s = preg_replace('/[^0-9,\.\-]/', '', $s);

  $hasComma = strpos($s, ',') !== false;
  $hasDot   = strpos($s, '.') !== false;

  if ($hasComma && $hasDot){
    // Asumimos formato AR: miles con . y decimales con ,
    $s = str_replace('.', '', $s);
    $s = str_replace(',', '.', $s);
  } elseif ($hasComma && !$hasDot){
    // Sólo coma -> decimales con coma
    $s = str_replace(',', '.', $s);
  } else {
    // Sólo punto o sin separadores -> ya está OK
  }
  return (float)$s;
}
?>