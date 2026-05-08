<?php
/**
 * Ejecutar en línea de comandos (o subir y abrir una vez en el navegador con protección):
 *   php tools/generar_hash_admin.php "TuNuevaClaveSegura"
 * Copia el hash a config/settings.php en la constante ADMIN_PASS.
 */
declare(strict_types=1);

$pass = $argv[1] ?? '';
if ($pass === '') {
    fwrite(STDERR, "Uso: php generar_hash_admin.php \"tu_clave\"\n");
    exit(1);
}
echo password_hash($pass, PASSWORD_DEFAULT) . PHP_EOL;
