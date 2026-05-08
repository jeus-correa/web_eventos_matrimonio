<?php
/**
 * Conexión PDO MySQL y creación automática de tablas si no existen.
 * Credenciales: ajustar según Hosty (usuario MySQL del cPanel).
 */
declare(strict_types=1);

/** XAMPP local (Workbench: 127.0.0.1, puerto 3306, usuario root) */
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'eventos_matrimonio';
const DB_USER = 'root';
/** En XAMPP suele ir vacío; si configuraste clave para root, ponla acá */
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

/**
 * Obtiene instancia PDO singleton.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    ensure_schema($pdo);
    return $pdo;
}

/**
 * Crea tablas e índices si no existen (idempotente).
 */
function ensure_schema(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  unique_code VARCHAR(32) NOT NULL,
  event_date DATE NULL,
  description TEXT NULL,
  cover_image VARCHAR(255) NULL,
  music_url VARCHAR(500) NULL,
  countdown_enabled TINYINT(1) NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  accent_color VARCHAR(7) NOT NULL DEFAULT '#c9a962',
  album_password_hash VARCHAR(255) NULL,
  qr_path VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_events_slug (slug),
  UNIQUE KEY uq_events_code (unique_code),
  KEY idx_events_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS media (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id INT UNSIGNED NOT NULL,
  guest_name VARCHAR(120) NOT NULL,
  stored_filename VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_kind ENUM('image','video') NOT NULL,
  likes_count INT UNSIGNED NOT NULL DEFAULT 0,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_media_event (event_id),
  KEY idx_media_created (created_at),
  CONSTRAINT fk_media_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS media_likes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  media_id INT UNSIGNED NOT NULL,
  visitor_hash CHAR(64) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_like (media_id, visitor_hash),
  CONSTRAINT fk_like_media FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS comments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  media_id INT UNSIGNED NOT NULL,
  guest_name VARCHAR(120) NOT NULL,
  body VARCHAR(500) NOT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_comments_media (media_id),
  CONSTRAINT fk_comments_media FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
}
