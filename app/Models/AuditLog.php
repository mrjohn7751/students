<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Xavfsizlik jurnali — yozuvlar faqat qo'shiladi, o'zgartirilmaydi.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['context' => 'array', 'created_at' => 'datetime'];
    }

    /**
     * Joriy so'rov va foydalanuvchi nomidan yozuv qo'shadi.
     *
     * @param  array<string, mixed>  $context
     */
    public static function record(string $action, ?string $subject = null, array $context = [], ?int $userId = null): self
    {
        $request = request();

        return self::query()->create([
            'user_id' => $userId ?? $request->user()?->id,
            'action' => $action,
            'subject' => $subject,
            'context' => $context ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
