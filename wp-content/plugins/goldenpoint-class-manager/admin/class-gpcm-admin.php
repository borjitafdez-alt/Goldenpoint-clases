<?php

if (!defined('ABSPATH')) {
    exit;
}

class GPCM_Admin
{
    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
    }

    public function registerMenu(): void
    {
        add_menu_page(
            'GoldenPoint Clases',
            'GoldenPoint Clases',
            'gpcm_manage_all',
            'gpcm-dashboard',
            [$this, 'renderDashboard'],
            'dashicons-groups',
            3
        );

        $pages = [
            'Alumnos' => 'gpcm-students',
            'Grupos y Horarios' => 'gpcm-groups',
            'Asistencias' => 'gpcm-attendance',
            'Recuperaciones' => 'gpcm-recoveries',
            'Pagos' => 'gpcm-payments',
            'Exportación' => 'gpcm-exports',
        ];

        foreach ($pages as $title => $slug) {
            add_submenu_page('gpcm-dashboard', $title, $title, 'gpcm_manage_all', $slug, [$this, 'renderPlaceholder']);
        }
    }

    public function renderDashboard(): void
    {
        $this->guardAccess();
        echo '<div class="wrap"><h1>GoldenPoint Class Manager</h1>';
        echo '<p>Panel interno inicial para gestión de clases, asistencias, recuperaciones y pagos.</p>';
        echo '<h2>Flujos principales</h2>';
        echo '<ol>';
        echo '<li>Administrador crea grupo y asigna sede, pista, monitor, nivel y horario.</li>';
        echo '<li>Administrador asigna alumnos al grupo y revisa plazas libres.</li>';
        echo '<li>Monitor pasa asistencia por clase: asistente, falta avisada, falta sin avisar o recuperada.</li>';
        echo '<li>Sistema genera saldo de recuperación solo en faltas avisadas con +24h.</li>';
        echo '<li>Alumno usa recuperaciones en plazas libres compatibles con su modalidad.</li>';
        echo '<li>Administrador controla pagos mensuales, bonos y clases extra para cobro.</li>';
        echo '</ol>';
        echo '</div>';
    }

    public function renderPlaceholder(): void
    {
        $this->guardAccess();
        echo '<div class="wrap"><h1>Módulo en construcción</h1><p>Pantalla base creada. Próximo paso: CRUD y filtros avanzados.</p></div>';
    }

    private function guardAccess(): void
    {
        if (!current_user_can('gpcm_manage_all')) {
            wp_die('No tienes permisos para acceder a GoldenPoint Class Manager.');
        }
    }
}
