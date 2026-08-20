<?php
/**
 * Copia este archivo a wp-config.php
 * Replica local — Ticket SR-108688 (celularesenventa.cl)
 *
 * WordPress 7.0.3 + WooCommerce 11.0.1 + PHP 8.4.19
 */

define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', 'wordpress' );
define( 'DB_PASSWORD', 'wordpress' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'DB_ENGINE', 'sqlite' );
define( 'DB_DIR', __DIR__ . '/wp-content/database/' );
define( 'DB_FILE', '.ht.sqlite' );

define( 'AUTH_KEY',         'c87fd37858ca3d531e8bdf10bbcb88fd-auth-key' );
define( 'SECURE_AUTH_KEY',  'c87fd37858ca3d531e8bdf10bbcb88fd-secure-auth' );
define( 'LOGGED_IN_KEY',    'c87fd37858ca3d531e8bdf10bbcb88fd-logged-in' );
define( 'NONCE_KEY',        'c87fd37858ca3d531e8bdf10bbcb88fd-nonce-key' );
define( 'AUTH_SALT',        'c87fd37858ca3d531e8bdf10bbcb88fd-auth-salt' );
define( 'SECURE_AUTH_SALT', 'c87fd37858ca3d531e8bdf10bbcb88fd-secure-salt' );
define( 'LOGGED_IN_SALT',   'c87fd37858ca3d531e8bdf10bbcb88fd-logged-salt' );
define( 'NONCE_SALT',       'c87fd37858ca3d531e8bdf10bbcb88fd-nonce-salt' );

$table_prefix = 'wp_';

define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', true );
define( 'SCRIPT_DEBUG', false );

define( 'WP_HOME', 'http://127.0.0.1:8080' );
define( 'WP_SITEURL', 'http://127.0.0.1:8080' );
define( 'WPLANG', 'es_CL' );

define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'WP_AUTO_UPDATE_CORE', false );
define( 'DISALLOW_FILE_EDIT', false );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';
