# Recuerdos en vivo — Plataforma de galería para eventos

Aplicación en **PHP 8**, **MySQL**, **HTML/CSS/JS** pensada para **hosting compartido** (Hosty, cPanel, etc.): invitados escanean un **QR**, suben **fotos y videos**, ven una **galería actualizada por AJAX**, reaccionan, comentan y descargan recuerdos. Panel **admin** protegido para crear eventos, moderar, **ZIP** y **estadísticas**.

## Requisitos del servidor

- PHP **8.0+** con extensiones: `pdo_mysql`, `fileinfo`, `gd`, `zip` (para descarga ZIP), `json`, `session`.
- **MySQL** 5.7+ / MariaDB 10+.
- **Composer** (en tu PC o por SSH en el hosting) para instalar la librería de QR.
- Apache con `mod_rewrite` opcional; permisos de escritura en `uploads/` y `qrcodes/`.

## Instalación paso a paso (Hosty / cPanel)

1. **Crear base de datos** en cPanel → MySQL® Databases: anota nombre de BD, usuario y contraseña.

2. **Sube los archivos** del proyecto a `public_html` (o la carpeta de tu dominio).

3. **Configura la base de datos** editando `config/database.php`:
   - `DB_HOST` (suele ser `localhost`)
   - `DB_NAME`, `DB_USER`, `DB_PASS`

4. **Configura el sitio**:
   - Copia `config/settings.sample.php` a `config/settings.php` (si aún no existe).
   - En `settings.php` define **`SITE_URL`** sin barra final, por ejemplo: `https://tudominio.cl`
   - Cambia **`ADMIN_PASS`** (texto plano para pruebas o, recomendado, un hash bcrypt).
   - Cambia **`APP_SECRET`** por una cadena larga aleatoria (sirve para tokens de subida).

5. **Generar hash de admin** (recomendado en producción), en SSH:

   ```bash
   php tools/generar_hash_admin.php "TuClaveMuySegura"
   ```

   Pega el resultado en `ADMIN_PASS` dentro de `config/settings.php`.

6. **Instalar dependencias PHP (QR)** en la carpeta del proyecto:

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

   Si no tienes SSH, instala Composer en tu PC, ejecuta `composer install`, y sube la carpeta **`vendor/`** completa junto al resto del proyecto.

7. **Permisos** (si hace falta):

   ```bash
   chmod 755 uploads qrcodes
   ```

   La aplicación creará subcarpetas por evento dentro de `uploads/`.

8. **Primera visita**: abre el sitio en el navegador. Las tablas MySQL se crean **automáticamente** la primera vez que se conecta la app (también puedes importar `database.sql` manualmente si prefieres).

9. Entra a **`/admin/login.php`**, usa la clave configurada en `ADMIN_PASS`, crea un evento y descarga el **QR PNG** desde el panel.

## Estructura principal

- `index.php` — Portada y acceso por código o slug.
- `event.php` — Página del evento (hero, cuenta regresiva, música, subida, galería, modo TV, álbum con contraseña opcional).
- `upload.php` — Subida JSON (validación MIME, límites 10 MB / 100 MB, compresión de imágenes).
- `gallery.php` — Listado JSON para la galería en tiempo real (polling).
- `like.php` / `comment.php` / `comments.php` — Reacciones y comentarios.
- `admin/` — Panel (eventos, moderación, ZIP, QR).
- `config/` — Base de datos y ajustes (protegido por `.htaccess`).
- `assets/` — CSS/JS/imagenes.
- `uploads/` / `qrcodes/` — Contenido generado (protegidos con `.htaccess`).

## Seguridad (resumen)

- Consultas con **PDO** preparadas.
- Validación de **MIME** con `finfo`.
- Nombres de archivo renombrados; carpetas por `event_id`.
- Sin ejecución de PHP en `uploads/`.
- Tokens **HMAC** para subida y API de galería (el token viaja en la página del evento; el álbum privado añade capa en la entrada).
- Cabeceras básicas en `.htaccess`.

## PWA y notificaciones

- `manifest.webmanifest` y `sw.js` permiten instalación ligera en el móvil.
- El navegador puede pedir permiso para **notificaciones** cuando llegan fotos nuevas (opcional).

## Licencia y uso

Código de ejemplo para uso en tus proyectos y eventos. Ajusta textos legales y política de privacidad según tu país y tipo de evento.
