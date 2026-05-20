<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once GPCM_PATH . 'includes/class-gpcm-activator.php';
require_once GPCM_PATH . 'includes/class-gpcm-roles.php';
require_once GPCM_PATH . 'includes/class-gpcm-attendance-service.php';
require_once GPCM_PATH . 'includes/class-gpcm-form-integration.php';
require_once GPCM_PATH . 'admin/class-gpcm-admin.php';

class GPCM_Plugin
{
    public function run(): void
    {
        register_activation_hook(GPCM_FILE, ['GPCM_Activator', 'activate']);
        register_deactivation_hook(GPCM_FILE, ['GPCM_Roles', 'removeCustomRoles']);

        add_action('init', ['GPCM_Roles', 'register']);

        $integration = new GPCM_Form_Integration();
        $integration->registerHooks();

        if (is_admin()) {
            $admin = new GPCM_Admin();
            $admin->registerHooks();
        }
    }
}
