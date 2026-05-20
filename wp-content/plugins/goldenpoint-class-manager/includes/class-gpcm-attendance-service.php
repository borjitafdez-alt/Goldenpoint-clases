<?php

if (!defined('ABSPATH')) {
    exit;
}

class GPCM_Attendance_Service
{
    public const STATUS_ATTENDED = 'attended';
    public const STATUS_MISSED_NOTIFIED = 'missed_notified';
    public const STATUS_MISSED_UNNOTIFIED = 'missed_unnotified';
    public const STATUS_CANCELLED_24_PLUS = 'cancelled_24_plus';
    public const STATUS_CANCELLED_24_MINUS = 'cancelled_24_minus';
    public const STATUS_RECOVERY = 'recovery';
    public const STATUS_EXTRA = 'extra';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_ATTENDED => 'Asiste',
            self::STATUS_MISSED_NOTIFIED => 'Falta avisada',
            self::STATUS_MISSED_UNNOTIFIED => 'Falta sin avisar',
            self::STATUS_CANCELLED_24_PLUS => 'Cancelación +24h',
            self::STATUS_CANCELLED_24_MINUS => 'Cancelación -24h',
            self::STATUS_RECOVERY => 'Recuperación',
            self::STATUS_EXTRA => 'Clase extra',
        ];
    }

    public static function calculateFlags(string $status): array
    {
        $consumes = true;
        $recovery = false;

        if (in_array($status, [self::STATUS_CANCELLED_24_PLUS], true)) {
            $consumes = false;
            $recovery = true;
        }

        if (in_array($status, [self::STATUS_MISSED_NOTIFIED], true)) {
            // Falta avisada por defecto se consume, solo se libera si se marca explícitamente +24h
            $consumes = true;
        }

        if (in_array($status, [self::STATUS_RECOVERY], true)) {
            $consumes = false;
        }

        return [
            'consumes_class' => $consumes ? 1 : 0,
            'generates_recovery' => $recovery ? 1 : 0,
        ];
    }

    public static function canEditClass(array $classRow, int $userId): bool
    {
        if (current_user_can('gpcm_manage_all')) {
            return true;
        }

        if (!current_user_can('gpcm_manage_attendance')) {
            return false;
        }

        return (int) $classRow['monitor_user_id'] === $userId;
    }
}
