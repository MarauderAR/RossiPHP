INSTALACIÓN RÁPIDA
1) Crear DB y tablas:
   - Importá el archivo schema.sql en MySQL.

2) Subí la carpeta al servidor (por ejemplo /gdc/).

3) Ajustes:
   - En config.php ya están las credenciales para db_gdc (usuario empresa / v3t3r4n0).
   - Si cambiás rutas o dominios, no hace falta tocar nada más.

4) Uso:
   - /gdc/index.php  -> alta + filtros + imprimir.
   - /gdc/token_new.php (opcional con ?proveedor=...&desde=... etc) -> genera link de solo lectura.
   - /gdc/view.php?token=XXXX -> vista de consulta/imprimir, sin editar.

5) Imprimir/PDF:
   - Botón "Imprimir / PDF" abre la vista limpia, usá "Guardar como PDF".
