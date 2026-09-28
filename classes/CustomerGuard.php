<?php
/**
 * CustomerGuard
 * ตรวจสอบความถูกต้องและเงื่อนไขตามกฎหมาย เช่น อายุต้อง >= 20 ปีบริบูรณ์
 */
class CustomerGuard {
    public static function calculateAge(string $birthdate): int {
        $dob = new DateTime($birthdate);
        $today = new DateTime();
        $diff = $today->diff($dob);
        return $diff->y;
    }

    public static function verifyAge(string $birthdate): bool {
        return self::calculateAge($birthdate) >= 20;
    }
}
