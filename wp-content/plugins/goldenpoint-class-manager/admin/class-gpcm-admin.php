<?php
if (!defined('ABSPATH')) {
    exit;
}

class GPCM_Admin
{
    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_post_gpcm_convert_user_student', [$this, 'handleConvertUserStudent']);
        add_action('admin_post_gpcm_convert_inscription', [$this, 'handleConvertInscription']);
        add_action('admin_post_gpcm_save_mapping', [$this, 'handleSaveMapping']);
        add_action('admin_post_gpcm_waitlist_assign', [$this, 'handleWaitlistAssign']);
    }

    public function registerMenu(): void
    {
        add_menu_page('GoldenPoint Clases', 'GoldenPoint Clases', 'read', 'gpcm-dashboard', [$this, 'renderDashboard'], 'dashicons-groups', 3);
        add_submenu_page('gpcm-dashboard', 'Alumnos WP', 'Alumnos WP', 'gpcm_manage_all', 'gpcm-wp-users', [$this, 'renderWpUsers']);
        add_submenu_page('gpcm-dashboard', 'Inscripciones', 'Inscripciones', 'gpcm_manage_all', 'gpcm-inscriptions', [$this, 'renderInscriptions']);
        add_submenu_page('gpcm-dashboard', 'Lista de espera', 'Lista de espera', 'gpcm_manage_all', 'gpcm-waitlist', [$this, 'renderWaitlist']);
        add_submenu_page('gpcm-dashboard', 'Mapeo formularios', 'Mapeo formularios', 'gpcm_manage_all', 'gpcm-mapping', [$this, 'renderMapping']);
        add_submenu_page('gpcm-dashboard', 'Ficha alumno', 'Ficha alumno', 'gpcm_manage_all', 'gpcm-student-profile', [$this, 'renderStudentProfile']);
    }

    public function renderDashboard(): void
    {
        echo '<div class="wrap"><h1>GoldenPoint Class Manager</h1><p>Fase 5: integración con usuarios WordPress e inscripciones.</p></div>';
    }

    public function renderWpUsers(): void
    {
        $this->guardAdmin();
        global $wpdb;
        $p = $wpdb->prefix . 'gpcm_';
        $q = sanitize_text_field($_GET['q'] ?? '');
        $sql = "SELECT u.ID, u.display_name, u.user_email,
                MAX(CASE WHEN um.meta_key='first_name' THEN um.meta_value END) first_name,
                MAX(CASE WHEN um.meta_key='last_name' THEN um.meta_value END) last_name,
                MAX(CASE WHEN um.meta_key='phone' THEN um.meta_value END) phone
                FROM {$wpdb->users} u
                LEFT JOIN {$wpdb->usermeta} um ON um.user_id=u.ID";
        $where = '';
        if ($q !== '') {
            $like = '%' . $wpdb->esc_like($q) . '%';
            $where = $wpdb->prepare(' WHERE u.display_name LIKE %s OR u.user_email LIKE %s OR um.meta_value LIKE %s', $like, $like, $like);
        }
        $rows = $wpdb->get_results($sql . $where . ' GROUP BY u.ID ORDER BY u.ID DESC LIMIT 200', ARRAY_A);

        echo '<div class="wrap"><h1>Usuarios WordPress</h1><form method="get"><input type="hidden" name="page" value="gpcm-wp-users"/><input type="text" name="q" value="' . esc_attr($q) . '" placeholder="nombre, email o teléfono"/>';
        submit_button('Buscar', 'secondary', '', false);
        echo '</form><table class="widefat"><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Acción</th></tr>';
        foreach ($rows as $r) {
            echo '<tr><td>' . (int) $r['ID'] . '</td><td>' . esc_html(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: $r['display_name']) . '</td><td>' . esc_html($r['user_email']) . '</td><td>' . esc_html($r['phone'] ?? '') . '</td><td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('gpcm_convert_user_' . (int) $r['ID']);
            echo '<input type="hidden" name="action" value="gpcm_convert_user_student"/><input type="hidden" name="wp_user_id" value="' . (int) $r['ID'] . '"/>';
            submit_button('Convertir en alumno', 'secondary', '', false);
            echo '</form></td></tr>';
        }
        echo '</table></div>';
    }

    public function handleConvertUserStudent(): void
    {
        $this->guardAdmin();
        global $wpdb;
        $p = $wpdb->prefix . 'gpcm_';
        $uid = (int) ($_POST['wp_user_id'] ?? 0);
        check_admin_referer('gpcm_convert_user_' . $uid);
        if ($uid < 1) {
            wp_die('Usuario inválido.');
        }
        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}students WHERE wp_user_id=%d", $uid));
        $now = current_time('mysql');
        if ($exists === 0) {
            $phone = (string) get_user_meta($uid, 'phone', true);
            $wpdb->insert("{$p}students", ['wp_user_id' => $uid, 'phone' => sanitize_text_field($phone), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        }
        wp_safe_redirect(admin_url('admin.php?page=gpcm-wp-users'));
        exit;
    }

    public function renderInscriptions(): void
    {
        $this->guardAdmin();
        global $wpdb;
        $p = $wpdb->prefix . 'gpcm_';
        $rows = $wpdb->get_results("SELECT * FROM {$p}inscriptions ORDER BY submitted_at DESC LIMIT 300", ARRAY_A);
        $levels = $wpdb->get_results("SELECT id,name FROM {$p}levels ORDER BY sort_order", ARRAY_A);
        $venues = $wpdb->get_results("SELECT id,name FROM {$p}venues ORDER BY name", ARRAY_A);
        $groups = $wpdb->get_results("SELECT id,name FROM {$p}groups WHERE active=1 ORDER BY name", ARRAY_A);

        echo '<div class="wrap"><h1>Inscripciones</h1><table class="widefat"><tr><th>Fecha</th><th>Nombre</th><th>Email</th><th>Estado</th><th>Acción</th></tr>';
        foreach ($rows as $r) {
            echo '<tr><td>' . esc_html($r['submitted_at']) . '</td><td>' . esc_html(trim($r['first_name'] . ' ' . $r['last_name'])) . '</td><td>' . esc_html($r['email']) . '</td><td>' . esc_html($r['status']) . '</td><td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('gpcm_convert_inscription_' . (int) $r['id']);
            echo '<input type="hidden" name="action" value="gpcm_convert_inscription"/><input type="hidden" name="inscription_id" value="' . (int) $r['id'] . '"/>';
            echo '<select name="level_id"><option value="">Nivel</option>';
            foreach ($levels as $l) { echo '<option value="' . (int) $l['id'] . '">' . esc_html($l['name']) . '</option>'; }
            echo '</select> <select name="venue_id"><option value="">Sede</option>';
            foreach ($venues as $v) { echo '<option value="' . (int) $v['id'] . '">' . esc_html($v['name']) . '</option>'; }
            echo '</select> <select name="group_id"><option value="">Grupo</option>';
            foreach ($groups as $g) { echo '<option value="' . (int) $g['id'] . '">' . esc_html($g['name']) . '</option>'; }
            echo '</select> <select name="target_status"><option value="active">Asignar alumno</option><option value="pending">Pendiente plaza</option><option value="waitlist">Lista de espera</option></select>';
            submit_button('Convertir', 'secondary', '', false);
            echo '</form></td></tr>';
        }
        echo '</table></div>';
    }

    public function handleConvertInscription(): void
    {
        $this->guardAdmin();
        global $wpdb; $p=$wpdb->prefix.'gpcm_';
        $id=(int)($_POST['inscription_id']??0); check_admin_referer('gpcm_convert_inscription_'.$id);
        $ins=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}inscriptions WHERE id=%d",$id),ARRAY_A); if(!$ins){wp_die('Inscripción no encontrada');}
        $email=sanitize_email((string)$ins['email']); $uid=(int)$ins['wp_user_id'];
        if($uid===0 && $email!==''){ $u=get_user_by('email',$email); if($u){$uid=(int)$u->ID;} }
        if($uid===0){ wp_die('No existe usuario WP asociado por email/wp_user_id'); }
        $now=current_time('mysql');
        $studentId=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}students WHERE wp_user_id=%d",$uid));
        $data=[
            'wp_user_id'=>$uid,'phone'=>sanitize_text_field((string)$ins['phone']),'dni'=>sanitize_text_field((string)$ins['dni']),
            'birth_date'=>!empty($ins['birth_date'])?sanitize_text_field((string)$ins['birth_date']):null,
            'level_id'=>(int)($_POST['level_id']??0)?:null,'preferred_venue_id'=>(int)($_POST['venue_id']??0)?:null,
            'status'=>sanitize_text_field($_POST['target_status']??'pending'),'observations'=>sanitize_textarea_field((string)$ins['observations']),'updated_at'=>$now,
        ];
        if($studentId>0){ $wpdb->update("{$p}students",$data,['id'=>$studentId]); }
        else { $data['created_at']=$now; $wpdb->insert("{$p}students",$data); $studentId=(int)$wpdb->insert_id; }

        $groupId=(int)($_POST['group_id']??0);
        if($groupId>0 && $data['status']==='active'){
            $exists=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}group_students WHERE group_id=%d AND student_user_id=%d AND status='active'",$groupId,$uid));
            if($exists===0){$wpdb->insert("{$p}group_students",['group_id'=>$groupId,'student_user_id'=>$uid,'joined_at'=>$now,'status'=>'active','created_at'=>$now,'updated_at'=>$now]);}
        }
        if($data['status']==='waitlist'){
            $wpdb->insert("{$p}waitlist",['student_user_id'=>$uid,'level_id'=>$data['level_id'],'venue_id'=>$data['preferred_venue_id'],'class_type_id'=>null,'availability'=>sanitize_text_field((string)$ins['availability']),'requested_at'=>$now,'status'=>'waiting','observations'=>sanitize_textarea_field((string)$ins['observations']),'created_at'=>$now,'updated_at'=>$now]);
        }
        $wpdb->update("{$p}inscriptions",['wp_user_id'=>$uid,'status'=>$data['status'],'updated_at'=>$now],['id'=>$id]);
        wp_safe_redirect(admin_url('admin.php?page=gpcm-inscriptions')); exit;
    }

    public function renderWaitlist(): void
    {
        $this->guardAdmin(); global $wpdb; $p=$wpdb->prefix.'gpcm_';
        $rows=$wpdb->get_results("SELECT w.*,u.display_name FROM {$p}waitlist w INNER JOIN {$wpdb->users} u ON u.ID=w.student_user_id WHERE w.status='waiting' ORDER BY w.priority ASC,w.requested_at ASC",ARRAY_A);
        echo '<div class="wrap"><h1>Lista de espera</h1><table class="widefat"><tr><th>Alumno</th><th>Fecha</th><th>Prioridad</th><th>Estado</th><th>Acción</th></tr>';
        foreach($rows as $r){
            echo '<tr><td>'.esc_html($r['display_name']).'</td><td>'.esc_html($r['requested_at']).'</td><td>'.(int)$r['priority'].'</td><td>'.esc_html($r['status']).'</td><td><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            wp_nonce_field('gpcm_waitlist_assign_'.(int)$r['id']);
            echo '<input type="hidden" name="action" value="gpcm_waitlist_assign"/><input type="hidden" name="waitlist_id" value="'.(int)$r['id'].'"/><input type="number" min="1" name="group_id" placeholder="ID grupo" required/>';
            submit_button('Asignar a grupo','secondary','',false); echo '</form></td></tr>';
        }
        echo '</table></div>';
    }

    public function handleWaitlistAssign(): void
    {
        $this->guardAdmin(); global $wpdb; $p=$wpdb->prefix.'gpcm_';
        $id=(int)($_POST['waitlist_id']??0); $groupId=(int)($_POST['group_id']??0); check_admin_referer('gpcm_waitlist_assign_'.$id);
        $w=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}waitlist WHERE id=%d",$id),ARRAY_A); if(!$w){wp_die('No encontrado');}
        $now=current_time('mysql');
        $exists=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}group_students WHERE group_id=%d AND student_user_id=%d AND status='active'",$groupId,$w['student_user_id']));
        if($exists===0){$wpdb->insert("{$p}group_students",['group_id'=>$groupId,'student_user_id'=>(int)$w['student_user_id'],'joined_at'=>$now,'status'=>'active','created_at'=>$now,'updated_at'=>$now]);}
        $wpdb->update("{$p}waitlist",['status'=>'assigned','updated_at'=>$now],['id'=>$id]);
        $wpdb->update("{$p}students",['status'=>'active','updated_at'=>$now],['wp_user_id'=>(int)$w['student_user_id']]);
        wp_safe_redirect(admin_url('admin.php?page=gpcm-waitlist')); exit;
    }

    public function renderMapping(): void
    {
        $this->guardAdmin();
        $m=get_option('gpcm_field_mapping',[]);
        $fields=['form_id','first_name','last_name','email','phone','dni','birth_date','level','venue','class_type','availability','available_days','observations','season','registration_status'];
        echo '<div class="wrap"><h1>Mapeo de formularios</h1><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('gpcm_save_mapping'); echo '<input type="hidden" name="action" value="gpcm_save_mapping"/><table class="form-table">';
        foreach($fields as $f){ echo '<tr><th>'.esc_html($f).'</th><td><input type="text" name="map['.esc_attr($f).']" value="'.esc_attr((string)($m[$f]??$f)).'" class="regular-text"/></td></tr>'; }
        echo '</table>'; submit_button('Guardar mapeo'); echo '</form><p>Hooks: <code>gpcm_forminator_submission_data</code>, <code>gpcm_mapped_inscription_data</code>, <code>gpcm_before_store_inscription</code>.</p></div>';
    }

    public function handleSaveMapping(): void
    {
        $this->guardAdmin(); check_admin_referer('gpcm_save_mapping');
        $map=(array)($_POST['map']??[]); $clean=[]; foreach($map as $k=>$v){$clean[sanitize_key($k)]=sanitize_text_field((string)$v);} update_option('gpcm_field_mapping',$clean);
        wp_safe_redirect(admin_url('admin.php?page=gpcm-mapping')); exit;
    }

    public function renderStudentProfile(): void
    {
        $this->guardAdmin(); global $wpdb; $p=$wpdb->prefix.'gpcm_'; $uid=(int)($_GET['wp_user_id']??0);
        echo '<div class="wrap"><h1>Ficha alumno</h1><form method="get"><input type="hidden" name="page" value="gpcm-student-profile"/><input type="number" name="wp_user_id" min="1" value="'.$uid.'" placeholder="WP User ID"/>'; submit_button('Ver','secondary','',false); echo '</form>';
        if($uid>0){
            $student=$wpdb->get_row($wpdb->prepare("SELECT s.*,u.display_name,u.user_email FROM {$p}students s INNER JOIN {$wpdb->users} u ON u.ID=s.wp_user_id WHERE s.wp_user_id=%d",$uid),ARRAY_A);
            if($student){
                echo '<h2>'.esc_html($student['display_name']).' ('.esc_html($student['user_email']).')</h2><p>Tel: '.esc_html((string)$student['phone']).' | DNI: '.esc_html((string)$student['dni']).' | Estado: '.esc_html((string)$student['status']).'</p>';
            }
        }
        echo '</div>';
    }

    private function guardAdmin(): void
    {
        if (!current_user_can('gpcm_manage_all')) {
            wp_die('No autorizado');
        }
    }
}
