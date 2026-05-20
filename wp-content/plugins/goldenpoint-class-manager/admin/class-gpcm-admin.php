<?php

if (!defined('ABSPATH')) {
    exit;
}

class GPCM_Admin
{
    private string $prefix;

    public function __construct()
    {
        global $wpdb;
        $this->prefix = $wpdb->prefix . 'gpcm_';
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_post_gpcm_save_group', [$this, 'saveGroup']);
        add_action('admin_post_gpcm_delete_group', [$this, 'deleteGroup']);
        add_action('admin_post_gpcm_save_schedule', [$this, 'saveSchedule']);
        add_action('admin_post_gpcm_delete_schedule', [$this, 'deleteSchedule']);
        add_action('admin_post_gpcm_assign_student', [$this, 'assignStudent']);
        add_action('admin_post_gpcm_move_student', [$this, 'moveStudent']);
    }

    public function registerMenu(): void
    {
        add_menu_page('GoldenPoint Clases', 'GoldenPoint Clases', 'gpcm_manage_all', 'gpcm-dashboard', [$this, 'renderDashboard'], 'dashicons-groups', 3);
        add_submenu_page('gpcm-dashboard', 'Dashboard', 'Dashboard', 'gpcm_manage_all', 'gpcm-dashboard', [$this, 'renderDashboard']);
        add_submenu_page('gpcm-dashboard', 'Grupos', 'Grupos', 'gpcm_manage_all', 'gpcm-groups', [$this, 'renderGroupsPage']);
        add_submenu_page('gpcm-dashboard', 'Horarios', 'Horarios', 'gpcm_manage_all', 'gpcm-schedules', [$this, 'renderSchedulesPage']);
        add_submenu_page('gpcm-dashboard', 'Alumnos', 'Alumnos', 'gpcm_manage_all', 'gpcm-students', [$this, 'renderStudentsPage']);
        add_submenu_page('gpcm-dashboard', 'Calendario Semanal', 'Calendario Semanal', 'gpcm_manage_all', 'gpcm-calendar', [$this, 'renderCalendarPage']);
    }

    public function renderDashboard(): void
    {
        $this->guardAccess();
        echo '<div class="wrap"><h1>GoldenPoint Class Manager</h1><p>Fase 1 activa: gestión real de grupos, horarios, alumnos y calendario semanal.</p></div>';
    }

    public function renderGroupsPage(): void
    {
        global $wpdb;
        $this->guardAccess();

        $groupId = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
        $group = $groupId ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->prefix}groups WHERE id = %d", $groupId)) : null;
        $levels = $wpdb->get_results("SELECT id, name FROM {$this->prefix}levels ORDER BY sort_order ASC");
        $venues = $wpdb->get_results("SELECT id, name FROM {$this->prefix}venues ORDER BY name ASC");
        $types = $wpdb->get_results("SELECT id, name FROM {$this->prefix}class_types ORDER BY name ASC");
        $monitors = get_users(['role' => 'gpcm_monitor']);

        $rows = $wpdb->get_results("SELECT g.*, l.name AS level_name, v.name AS venue_name, ct.name AS class_type_name, u.display_name AS monitor_name,
            (SELECT COUNT(*) FROM {$this->prefix}group_students gs WHERE gs.group_id = g.id AND gs.status = 'active') AS active_students
            FROM {$this->prefix}groups g
            LEFT JOIN {$this->prefix}levels l ON l.id = g.level_id
            LEFT JOIN {$this->prefix}venues v ON v.id = g.venue_id
            LEFT JOIN {$this->prefix}class_types ct ON ct.id = g.class_type_id
            LEFT JOIN {$wpdb->users} u ON u.ID = g.monitor_user_id
            ORDER BY g.id DESC");

        echo '<div class="wrap"><h1>CRUD de grupos</h1>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('gpcm_save_group');
        echo '<input type="hidden" name="action" value="gpcm_save_group"><input type="hidden" name="group_id" value="' . esc_attr($group->id ?? 0) . '">';
        echo '<table class="form-table"><tbody>';
        $this->inputRow('Nombre del grupo', '<input name="name" required class="regular-text" value="' . esc_attr($group->name ?? '') . '">');
        $this->inputRow('Nivel', $this->selectHtml('level_id', $levels, $group->level_id ?? 0));
        $this->inputRow('Sede', $this->selectHtml('venue_id', $venues, $group->venue_id ?? 0));
        $this->inputRow('Tipo de clase', $this->selectHtml('class_type_id', $types, $group->class_type_id ?? 0));
        $this->inputRow('Monitor', $this->usersSelectHtml('monitor_user_id', $monitors, $group->monitor_user_id ?? 0));
        $this->inputRow('Máximo plazas', '<input name="max_students" type="number" min="1" max="12" required value="' . esc_attr($group->max_students ?? 4) . '">');
        echo '</tbody></table><p><button class="button button-primary">' . ($group ? 'Actualizar grupo' : 'Crear grupo') . '</button></p></form>';

        echo '<h2>Listado de grupos</h2><table class="widefat striped"><thead><tr><th>ID</th><th>Grupo</th><th>Nivel</th><th>Sede</th><th>Tipo</th><th>Monitor</th><th>Plazas</th><th>Libres</th><th>Acciones</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $free = max((int) $row->max_students - (int) $row->active_students, 0);
            $editUrl = admin_url('admin.php?page=gpcm-groups&edit=' . (int) $row->id);
            echo '<tr><td>' . (int) $row->id . '</td><td>' . esc_html($row->name) . '</td><td>' . esc_html($row->level_name) . '</td><td>' . esc_html($row->venue_name) . '</td><td>' . esc_html($row->class_type_name) . '</td><td>' . esc_html($row->monitor_name ?: '-') . '</td><td>' . (int) $row->max_students . '</td><td><strong>' . $free . '</strong></td><td><a class="button" href="' . esc_url($editUrl) . '">Editar</a> ';
            echo '<form style="display:inline" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('gpcm_delete_group');
            echo '<input type="hidden" name="action" value="gpcm_delete_group"><input type="hidden" name="group_id" value="' . (int) $row->id . '"><button class="button button-link-delete" onclick="return confirm(\'¿Eliminar grupo?\')">Eliminar</button></form></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function renderSchedulesPage(): void
    {
        global $wpdb;
        $this->guardAccess();
        $groups = $wpdb->get_results("SELECT id,name FROM {$this->prefix}groups ORDER BY name ASC");
        $monitors = get_users(['role' => 'gpcm_monitor']);

        $rows = $wpdb->get_results("SELECT s.*, g.name AS group_name, u.display_name AS monitor_name
            FROM {$this->prefix}group_schedules s
            INNER JOIN {$this->prefix}groups g ON g.id=s.group_id
            LEFT JOIN {$wpdb->users} u ON u.ID=s.monitor_user_id
            ORDER BY s.day_of_week, s.start_time");

        echo '<div class="wrap"><h1>Horarios</h1><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('gpcm_save_schedule');
        echo '<input type="hidden" name="action" value="gpcm_save_schedule"><table class="form-table"><tbody>';
        $this->inputRow('Grupo', $this->selectHtml('group_id', $groups));
        $this->inputRow('Día', $this->daysSelect('day_of_week'));
        $this->inputRow('Hora inicio', '<input type="time" name="start_time" required>');
        $this->inputRow('Hora fin', '<input type="time" name="end_time" required>');
        $this->inputRow('Pista', '<input name="court" class="regular-text">');
        $this->inputRow('Monitor', $this->usersSelectHtml('monitor_user_id', $monitors));
        echo '</tbody></table><p><button class="button button-primary">Crear horario</button></p></form>';

        echo '<h2>Listado</h2><table class="widefat striped"><thead><tr><th>Día</th><th>Inicio</th><th>Fin</th><th>Pista</th><th>Grupo</th><th>Monitor</th><th></th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td>' . esc_html($this->dayName((int) $row->day_of_week)) . '</td><td>' . esc_html(substr($row->start_time, 0, 5)) . '</td><td>' . esc_html(substr($row->end_time, 0, 5)) . '</td><td>' . esc_html($row->court ?: '-') . '</td><td>' . esc_html($row->group_name) . '</td><td>' . esc_html($row->monitor_name ?: '-') . '</td><td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('gpcm_delete_schedule');
            echo '<input type="hidden" name="action" value="gpcm_delete_schedule"><input type="hidden" name="schedule_id" value="' . (int) $row->id . '"><button class="button button-link-delete">Eliminar</button></form>';
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function renderStudentsPage(): void
    {
        global $wpdb;
        $this->guardAccess();
        $groups = $wpdb->get_results("SELECT id,name,max_students FROM {$this->prefix}groups ORDER BY name ASC");
        $students = get_users(['role' => 'gpcm_student']);

        echo '<div class="wrap"><h1>Gestión de alumnos</h1>';
        echo '<h2>Añadir alumno al grupo / Lista de espera</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('gpcm_assign_student');
        echo '<input type="hidden" name="action" value="gpcm_assign_student">';
        $this->inputRow('Alumno', $this->usersSelectHtml('student_user_id', $students));
        $this->inputRow('Grupo', $this->selectHtml('group_id', $groups));
        $this->inputRow('Estado', '<select name="status"><option value="active">Activo</option><option value="waitlist">Lista de espera</option></select>');
        echo '<p><button class="button button-primary">Guardar</button></p></form>';

        echo '<h2>Mover alumno entre grupos</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('gpcm_move_student');
        echo '<input type="hidden" name="action" value="gpcm_move_student">';
        $this->inputRow('Alumno', $this->usersSelectHtml('student_user_id', $students));
        $this->inputRow('Grupo origen', $this->selectHtml('from_group_id', $groups));
        $this->inputRow('Grupo destino', $this->selectHtml('to_group_id', $groups));
        echo '<p><button class="button button-primary">Mover alumno</button></p></form>';

        echo '<h2>Plazas libres por grupo</h2><table class="widefat striped"><thead><tr><th>Grupo</th><th>Max</th><th>Activos</th><th>Lista espera</th><th>Plazas libres</th></tr></thead><tbody>';
        foreach ($groups as $group) {
            $active = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->prefix}group_students WHERE group_id=%d AND status='active'", $group->id));
            $wait = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->prefix}group_students WHERE group_id=%d AND status='waitlist'", $group->id));
            echo '<tr><td>' . esc_html($group->name) . '</td><td>' . (int) $group->max_students . '</td><td>' . $active . '</td><td>' . $wait . '</td><td><strong>' . max((int) $group->max_students - $active, 0) . '</strong></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function renderCalendarPage(): void
    {
        global $wpdb;
        $this->guardAccess();
        $rows = $wpdb->get_results("SELECT s.*, g.name AS group_name, v.name AS venue_name, u.display_name AS monitor_name
            FROM {$this->prefix}group_schedules s
            INNER JOIN {$this->prefix}groups g ON g.id = s.group_id
            INNER JOIN {$this->prefix}venues v ON v.id = g.venue_id
            LEFT JOIN {$wpdb->users} u ON u.ID = COALESCE(s.monitor_user_id, g.monitor_user_id)
            WHERE s.day_of_week BETWEEN 1 AND 5
            ORDER BY s.day_of_week, s.start_time, s.court");

        echo '<div class="wrap"><h1>Calendario semanal (Lunes a Viernes)</h1><table class="widefat striped"><thead><tr><th>Día</th><th>Hora</th><th>Pista</th><th>Grupo</th><th>Sede</th><th>Monitor</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td>' . esc_html($this->dayName((int) $row->day_of_week)) . '</td><td>' . esc_html(substr($row->start_time, 0, 5)) . ' - ' . esc_html(substr($row->end_time, 0, 5)) . '</td><td>' . esc_html($row->court ?: '-') . '</td><td>' . esc_html($row->group_name) . '</td><td>' . esc_html($row->venue_name) . '</td><td>' . esc_html($row->monitor_name ?: '-') . '</td></tr>';
        }
        if (!$rows) {
            echo '<tr><td colspan="6">No hay horarios creados todavía.</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function saveGroup(): void
    {
        $this->guardAccess();
        check_admin_referer('gpcm_save_group');
        global $wpdb;
        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'level_id' => absint($_POST['level_id'] ?? 0),
            'class_type_id' => absint($_POST['class_type_id'] ?? 0),
            'monitor_user_id' => absint($_POST['monitor_user_id'] ?? 0) ?: null,
            'venue_id' => absint($_POST['venue_id'] ?? 0),
            'max_students' => absint($_POST['max_students'] ?? 4),
            'updated_at' => current_time('mysql'),
        ];
        $groupId = absint($_POST['group_id'] ?? 0);
        if ($groupId) {
            $wpdb->update("{$this->prefix}groups", $data, ['id' => $groupId]);
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert("{$this->prefix}groups", $data);
        }
        wp_safe_redirect(admin_url('admin.php?page=gpcm-groups'));
        exit;
    }

    public function deleteGroup(): void
    {
        $this->guardAccess();
        check_admin_referer('gpcm_delete_group');
        global $wpdb;
        $groupId = absint($_POST['group_id'] ?? 0);
        if ($groupId) {
            $wpdb->delete("{$this->prefix}group_schedules", ['group_id' => $groupId]);
            $wpdb->delete("{$this->prefix}group_students", ['group_id' => $groupId]);
            $wpdb->delete("{$this->prefix}groups", ['id' => $groupId]);
        }
        wp_safe_redirect(admin_url('admin.php?page=gpcm-groups'));
        exit;
    }

    public function saveSchedule(): void
    {
        $this->guardAccess();
        check_admin_referer('gpcm_save_schedule');
        global $wpdb;
        $wpdb->insert("{$this->prefix}group_schedules", [
            'group_id' => absint($_POST['group_id'] ?? 0),
            'day_of_week' => absint($_POST['day_of_week'] ?? 1),
            'start_time' => sanitize_text_field($_POST['start_time'] ?? ''),
            'end_time' => sanitize_text_field($_POST['end_time'] ?? ''),
            'court' => sanitize_text_field($_POST['court'] ?? ''),
            'monitor_user_id' => absint($_POST['monitor_user_id'] ?? 0) ?: null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
        wp_safe_redirect(admin_url('admin.php?page=gpcm-schedules'));
        exit;
    }

    public function deleteSchedule(): void
    {
        $this->guardAccess();
        check_admin_referer('gpcm_delete_schedule');
        global $wpdb;
        $wpdb->delete("{$this->prefix}group_schedules", ['id' => absint($_POST['schedule_id'] ?? 0)]);
        wp_safe_redirect(admin_url('admin.php?page=gpcm-schedules'));
        exit;
    }

    public function assignStudent(): void
    {
        $this->guardAccess();
        check_admin_referer('gpcm_assign_student');
        global $wpdb;
        $wpdb->insert("{$this->prefix}group_students", [
            'group_id' => absint($_POST['group_id'] ?? 0),
            'student_user_id' => absint($_POST['student_user_id'] ?? 0),
            'status' => sanitize_text_field($_POST['status'] ?? 'active'),
            'joined_at' => current_time('mysql'),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
        wp_safe_redirect(admin_url('admin.php?page=gpcm-students'));
        exit;
    }

    public function moveStudent(): void
    {
        $this->guardAccess();
        check_admin_referer('gpcm_move_student');
        global $wpdb;
        $studentId = absint($_POST['student_user_id'] ?? 0);
        $from = absint($_POST['from_group_id'] ?? 0);
        $to = absint($_POST['to_group_id'] ?? 0);

        $wpdb->update("{$this->prefix}group_students", [
            'status' => 'moved',
            'left_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ], ['group_id' => $from, 'student_user_id' => $studentId, 'status' => 'active']);

        $wpdb->insert("{$this->prefix}group_students", [
            'group_id' => $to,
            'student_user_id' => $studentId,
            'status' => 'active',
            'joined_at' => current_time('mysql'),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        wp_safe_redirect(admin_url('admin.php?page=gpcm-students'));
        exit;
    }

    private function guardAccess(): void
    {
        if (!current_user_can('gpcm_manage_all')) {
            wp_die('No tienes permisos para acceder a GoldenPoint Class Manager.');
        }
    }

    private function inputRow(string $label, string $fieldHtml): void
    {
        echo '<table class="form-table"><tr><th><label>' . esc_html($label) . '</label></th><td>' . $fieldHtml . '</td></tr></table>';
    }

    private function selectHtml(string $name, array $rows, int $selected = 0): string
    {
        $html = '<select name="' . esc_attr($name) . '" required><option value="">Seleccionar</option>';
        foreach ($rows as $row) {
            $html .= '<option value="' . (int) $row->id . '" ' . selected((int) $row->id, $selected, false) . '>' . esc_html($row->name) . '</option>';
        }
        return $html . '</select>';
    }

    private function usersSelectHtml(string $name, array $users, int $selected = 0): string
    {
        $html = '<select name="' . esc_attr($name) . '"><option value="">Sin asignar</option>';
        foreach ($users as $user) {
            $html .= '<option value="' . (int) $user->ID . '" ' . selected((int) $user->ID, $selected, false) . '>' . esc_html($user->display_name) . '</option>';
        }
        return $html . '</select>';
    }

    private function daysSelect(string $name): string
    {
        $html = '<select name="' . esc_attr($name) . '">';
        for ($d = 1; $d <= 7; $d++) {
            $html .= '<option value="' . $d . '">' . esc_html($this->dayName($d)) . '</option>';
        }
        return $html . '</select>';
    }

    private function dayName(int $day): string
    {
        $days = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
        return $days[$day] ?? 'N/D';
    }
}
