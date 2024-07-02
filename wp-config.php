<?php
/**
 * Load secrets from AWS Secret Manager
 */
if ( file_exists( __DIR__ . '/wp-content/vendor/autoload.php' )) {
	require __DIR__ . '/wp-content/vendor/autoload.php';
}

use Aws\SecretsManager\SecretsManagerClient;

// Gather AWS Region from EC2 metadata
$ch = curl_init();
$url = 'http://169.254.169.254/latest/meta-data/placement/region';
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$aws_region= curl_exec($ch);
curl_close($ch);

$client = new SecretsManagerClient([
	'version' => '2017-10-17',
	'region' => $aws_region
]);

$result = $client->getSecretValue([
	'SecretId' => 'wordpress-ha-stack-secrets' // Name of the secret in ASM
]);

$secrets_data = $result['SecretString'];
$secrets = json_decode($secrets_data,true);
/**
 * ========================================
 */

/** Database credentials */
define( 'DB_HOST', $secrets['WP_DB_HOST'] );
define( 'DB_NAME', $secrets['WP_DB_NAME'] );
define( 'DB_USER', $secrets['WP_DB_USER'] );
define( 'DB_PASSWORD', $secrets['WP_DB_PASSWORD'] );

define( 'DB_SLAVE', $secrets['WP_DB_REPLICA'] );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

## Force HyperDB to use the correct db class instead of the deprecated one
define( 'WPDB_PATH', ABSPATH . 'wp-includes/class-wpdb.php' );

define('AUTH_KEY',         $secrets['WP_AUTH_KEY'] );
define('SECURE_AUTH_KEY',  $secrets['WP_SECURE_AUTH_KEY'] );
define('LOGGED_IN_KEY',    $secrets['WP_LOGGED_IN_KEY'] );
define('NONCE_KEY',        $secrets['WP_NONCE_KEY'] );
define('AUTH_SALT',        $secrets['WP_AUTH_SALT'] );
define('SECURE_AUTH_SALT', $secrets['WP_SECURE_AUTH_SALT'] );
define('LOGGED_IN_SALT',   $secrets['WP_LOGGED_IN_SALT'] );
define('NONCE_SALT',       $secrets['WP_NONCE_SALT'] );

$table_prefix  = 'wp_';

# WordPress core settings

# No plugin or core updates via the admin, and no file editor.
# All of this done via CI or WP-CLI
define( 'DISALLOW_FILE_MODS', true );

# Disable all automatic updates:
define( 'AUTOMATIC_UPDATER_DISABLED', true );

# Disable WP CRON
define( 'DISABLE_WP_CRON', true );

# Redis
define( "WP_CACHE", true );
define( 'WP_REDIS_PREFIX', 'wpsite1' );
define( 'WP_REDIS_HOST', $secrets['WP_REDIS_SERVER'] );
define( 'WP_REDIS_PORT', $secrets['WP_REDIS_PORT'] );
define( 'WP_REDIS_TIMEOUT', 1 );
define( 'WP_REDIS_READ_TIMEOUT', 1 );
define( 'WP_REDIS_DATABASE', 0 );

# S3
define( 'S3_UPLOADS_BUCKET', $secrets['S3_UPLOADS_BUCKET'] );
define( 'S3_UPLOADS_REGION', $aws_region );
define( 'S3_UPLOADS_USE_INSTANCE_PROFILE', true );

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
