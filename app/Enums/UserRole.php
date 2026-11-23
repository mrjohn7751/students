<?php

namespace App\Enums;

/**
 * Foydalanuvchi rollari.
 * Admin — hammasi; Staff — talabalar kartasi, sifat ro'yxatlari va eksport; Viewer — faqat umumiy statistika.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Staff = 'staff';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Staff => 'Xodim',
            self::Viewer => 'Kuzatuvchi',
        };
    }

    /** Talabalarning shaxsiy ma'lumotlarini (ro'yxat, karta, sifat ro'yxatlari) ko'ra oladimi. */
    public function canViewPersonalData(): bool
    {
        return $this !== self::Viewer;
    }

    /** Ma'lumotlarni CSV ko'rinishida yuklab ola oladimi. */
    public function canExport(): bool
    {
        return $this !== self::Viewer;
    }
}
