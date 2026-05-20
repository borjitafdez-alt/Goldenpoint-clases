<?php

if (!defined('ABSPATH')) {
    exit;
}

class GPCM_Roles
{
    public static function register(): void
    {
        add_role('gpcm_monitor', 'Monitor GoldenPoint', [
            'read' => true,
            'gpcm_view_own_classes' => true,
            'gpcm_manage_attendance' => true,
            'gpcm_view_students_progress' => true,
        ]);

        add_role('gpcm_student', 'Alumno GoldenPoint', [
            'read' => true,
            'gpcm_view_own_schedule' => true,
            'gpcm_manage_own_cancellations' => true,
            'gpcm_view_own_payments' => true,
        ]);

        $admin = get_role('administrator');
        if ($admin) {
            $caps = [
                'gpcm_manage_all',
                'gpcm_manage_groups',
                'gpcm_manage_payments',
                'gpcm_manage_recoveries',
                'gpcm_export_reports',
            ];

            foreach ($caps as $cap) {
                $admin->add_cap($cap);
            }
        }
    }

    public static function removeCustomRoles(): void
    {
        remove_role('gpcm_monitor');
        remove_role('gpcm_student');
    }
}
