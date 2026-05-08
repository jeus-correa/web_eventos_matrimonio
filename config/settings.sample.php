<?php
/**
 * Copiar este archivo a settings.php y ajustar valores.
 * settings.php no debe versionarse (contiene secretos).
 */
declare(strict_types=1);

/** URL pública del sitio (sin barra final). Ej: https://tudominio.cl */
const SITE_URL = 'https://tudominio.cl';

/** Zona horaria para fechas */
const APP_TIMEZONE = 'America/Santiago';

/**
 * Contraseña del panel admin.
 * Recomendado en producción: pegar resultado de password_hash('tu_clave', PASSWORD_DEFAULT).
 * Si el valor empieza por $2y$ o $argon2, se usa password_verify.
 */
const ADMIN_PASS = 'CambiaEstaClave123!';

/** Secreto para tokens de subida (cualquier cadena larga aleatoria) */
const APP_SECRET = 'cambia-este-secreto-por-uno-largo-y-aleatorio';

/** Límite subida imágenes en bytes (10 MB) */
const MAX_IMAGE_BYTES = 10 * 1024 * 1024;

/** Límite subida videos en bytes (100 MB) */
const MAX_VIDEO_BYTES = 100 * 1024 * 1024;

/** Intervalo polling galería (ms) en vista invitado */
const GALLERY_POLL_MS = 8000;

/** Versión de caché para assets estáticos (?v=) */
const ASSET_VERSION = '1';
