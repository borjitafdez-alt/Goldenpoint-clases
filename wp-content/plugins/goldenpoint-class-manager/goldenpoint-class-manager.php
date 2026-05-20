<?php
/**
 * Plugin Name: GoldenPoint Class Manager
 * Description: Gestión interna de clases, asistencias, recuperaciones y pagos para GoldenPoint Padel Academy.
 * Version: 0.1.0
 * Author: GoldenPoint
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GPCM_VERSION', '0.1.0');
define('GPCM_FILE', __FILE__);
define('GPCM_PATH', plugin_dir_path(__FILE__));
define('GPCM_URL', plugin_dir_url(__FILE__));

require_once GPCM_PATH . 'includes/class-gpcm-plugin.php';

function gpcm_bootstrap(): void
{
    $plugin = new GPCM_Plugin();
    $plugin->run();
}

gpcm_bootstrap();
