<?php

if (!defined('ABSPATH')) {
    exit;
}

class GPCM_Activator
{
    public static function activate(): void
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'gpcm_';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = [];

        $sql[] = "CREATE TABLE {$prefix}venues (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) {$charsetCollate};";

        $sql[] = "CREATE TABLE {$prefix}levels (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(80) NOT NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) {$charsetCollate};";

        $sql[] = "CREATE TABLE {$prefix}class_types (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(80) NOT NULL,
            capacity_min TINYINT UNSIGNED NOT NULL,
            capacity_max TINYINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) {$charsetCollate};";

        $sql[] = "CREATE TABLE {$prefix}groups (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            level_id BIGINT UNSIGNED NOT NULL,
            class_type_id BIGINT UNSIGNED NOT NULL,
            monitor_user_id BIGINT UNSIGNED NULL,
            venue_id BIGINT UNSIGNED NOT NULL,
            court VARCHAR(80) NULL,
            day_of_week TINYINT UNSIGNED NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            max_students TINYINT UNSIGNED NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY level_id (level_id),
            KEY class_type_id (class_type_id),
            KEY monitor_user_id (monitor_user_id),
            KEY venue_id (venue_id)
        ) {$charsetCollate};";

        $sql[] = "CREATE TABLE {$prefix}group_students (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id BIGINT UNSIGNED NOT NULL,
            student_user_id BIGINT UNSIGNED NOT NULL,
            joined_at DATETIME NOT NULL,
            left_at DATETIME NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_active_student_group (group_id, student_user_id, status),
            KEY student_user_id (student_user_id)
        ) {$charsetCollate};";

        $sql[] = "CREATE TABLE {$prefix}classes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id BIGINT UNSIGNED NOT NULL,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'scheduled',
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY group_id (group_id),
            KEY starts_at (starts_at)
        ) {$charsetCollate};";

        $sql[] = "CREATE TABLE {$prefix}attendance (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            class_id BIGINT UNSIGNED NOT NULL,
            student_user_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(30) NOT NULL,
            marked_by_user_id BIGINT UNSIGNED NULL,
            marked_at DATETIME NOT NULL,
            observation TEXT NULL,
            recovery_eligible TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_class_student (class_id, student_user_id),
            KEY student_user_id (student_user_id),
            KEY status (status)
        ) {$charsetCollate};";

        $sql[] = "CREATE TABLE {$prefix}recoveries (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_user_id BIGINT UNSIGNED NOT NULL,
            source_attendance_id BIGINT UNSIGNED NOT NULL,
            recovery_class_id BIGINT UNSIGNED NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            expires_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY student_user_id (student_user_id),
            KEY status (status)
        ) {$charsetCollate};";

        $sql[] = "CREATE TABLE {$prefix}student_plans (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_user_id BIGINT UNSIGNED NOT NULL,
            modality VARCHAR(40) NOT NULL,
            billing_type VARCHAR(30) NOT NULL,
            monthly_classes DECIMAL(5,2) NOT NULL DEFAULT 4,
            monthly_price DECIMAL(10,2) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            starts_on DATE NOT NULL,
            ends_on DATE NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY student_user_id (student_user_id)
        ) {$charsetCollate};";

        $sql[] = "CREATE TABLE {$prefix}wallets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_user_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(30) NOT NULL,
            total_classes SMALLINT UNSIGNED NOT NULL,
            consumed_classes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            expires_at DATE NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY student_user_id (student_user_id)
        ) {$charsetCollate};";

        $sql[] = "CREATE TABLE {$prefix}payments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_user_id BIGINT UNSIGNED NOT NULL,
            concept VARCHAR(120) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            status VARCHAR(40) NOT NULL,
            due_date DATE NULL,
            paid_at DATETIME NULL,
            method VARCHAR(40) NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY student_user_id (student_user_id),
            KEY status (status)
        ) {$charsetCollate};";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        self::seedDefaults($prefix);
        GPCM_Roles::register();
    }

    private static function seedDefaults(string $prefix): void
    {
        global $wpdb;

        $now = current_time('mysql');

        $venues = ['PadelPrix Oalma León', 'Casa de Asturias'];
        foreach ($venues as $venue) {
            $wpdb->insert("{$prefix}venues", ['name' => $venue, 'created_at' => $now, 'updated_at' => $now]);
        }

        $levels = ['Inicial', 'Inicial +', 'Inicial Medio', 'Inicial Medio +', 'Medio', 'Medio +', 'Avanzado', 'Competición'];
        foreach ($levels as $i => $level) {
            $wpdb->insert("{$prefix}levels", ['name' => $level, 'sort_order' => $i + 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        $types = [
            ['name' => 'Individual', 'capacity_min' => 1, 'capacity_max' => 1],
            ['name' => 'Reducida 2 personas', 'capacity_min' => 2, 'capacity_max' => 2],
            ['name' => 'Grupal 3-4 personas', 'capacity_min' => 3, 'capacity_max' => 4],
        ];

        foreach ($types as $type) {
            $wpdb->insert("{$prefix}class_types", [
                'name' => $type['name'],
                'capacity_min' => $type['capacity_min'],
                'capacity_max' => $type['capacity_max'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
