<?php

if (!defined('ABSPATH')) {
    exit;
}

class GPCM_Form_Integration
{
    public function registerHooks(): void
    {
        add_action('init', [$this, 'registerForminatorHooks']);
    }

    public function registerForminatorHooks(): void
    {
        add_action('forminator_custom_form_after_handle_submit', [$this, 'captureForminatorSubmission'], 10, 2);
    }

    public function captureForminatorSubmission($entry, $formId): void
    {
        $settings = get_option('gpcm_field_mapping', []);
        $targetFormId = (int) ($settings['form_id'] ?? 0);
        if ($targetFormId > 0 && (int) $formId !== $targetFormId) {
            return;
        }

        $raw = apply_filters('gpcm_forminator_submission_data', (array) $entry, $formId, $settings);
        $mapped = $this->mapFields($raw, $settings);
        do_action('gpcm_before_store_inscription', $mapped, $raw, $formId);
        $this->storeInscription($mapped, 'forminator', (string) $formId);
    }

    public function mapFields(array $raw, array $settings): array
    {
        $keys = [
            'first_name','last_name','email','phone','dni','birth_date','level','venue','class_type',
            'availability','available_days','observations','season','registration_status',
        ];
        $mapped = [];
        foreach ($keys as $k) {
            $fieldKey = $settings[$k] ?? $k;
            $mapped[$k] = sanitize_text_field((string) ($raw[$fieldKey] ?? ''));
        }
        $mapped['email'] = sanitize_email($mapped['email']);
        return apply_filters('gpcm_mapped_inscription_data', $mapped, $raw, $settings);
    }

    public function storeInscription(array $mapped, string $source, string $sourceId): void
    {
        global $wpdb;
        $p = $wpdb->prefix . 'gpcm_';
        $now = current_time('mysql');

        $wpUserId = 0;
        if (!empty($mapped['email'])) {
            $u = get_user_by('email', $mapped['email']);
            $wpUserId = $u ? (int) $u->ID : 0;
        }

        $existing = 0;
        if ($wpUserId > 0) {
            $existing = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}inscriptions WHERE wp_user_id = %d ORDER BY id DESC LIMIT 1", $wpUserId));
        }
        if ($existing === 0 && !empty($mapped['email'])) {
            $existing = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$p}inscriptions WHERE email = %s ORDER BY id DESC LIMIT 1", $mapped['email']));
        }

        $payload = [
            'wp_user_id' => $wpUserId ?: null,
            'first_name' => $mapped['first_name'],
            'last_name' => $mapped['last_name'],
            'email' => $mapped['email'],
            'phone' => $mapped['phone'],
            'dni' => $mapped['dni'],
            'birth_date' => $mapped['birth_date'] ?: null,
            'level_name' => $mapped['level'],
            'venue_name' => $mapped['venue'],
            'class_type_name' => $mapped['class_type'],
            'availability' => $mapped['availability'],
            'available_days' => $mapped['available_days'],
            'observations' => $mapped['observations'],
            'season' => $mapped['season'],
            'status' => $mapped['registration_status'] ?: 'pending',
            'source_plugin' => $source,
            'source_form_id' => $sourceId,
            'submitted_at' => $now,
            'updated_at' => $now,
        ];

        if ($existing > 0) {
            $wpdb->update("{$p}inscriptions", $payload, ['id' => $existing]);
        } else {
            $wpdb->insert("{$p}inscriptions", $payload);
        }
    }
}
