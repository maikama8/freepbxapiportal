<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordHistory extends Model
{
    protected $fillable = [
        'user_id',
        'password_hash',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Disable updated_at timestamp
     */
    public $timestamps = false;

    /**
     * Get the user that owns the password history
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if a password was used recently
     */
    public static function wasPasswordUsedRecently(int $userId, string $password, int $historyCount = 5): bool
    {
        $recentPasswords = static::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($historyCount)
            ->pluck('password_hash');

        foreach ($recentPasswords as $hash) {
            if (password_verify($password, $hash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add password to history
     */
    public static function addPasswordToHistory(int $userId, string $passwordHash): void
    {
        static::create([
            'user_id' => $userId,
            'password_hash' => $passwordHash,
            'created_at' => now(),
        ]);

        // Keep only the last N passwords
        $historyCount = config('voip.security.password.history_count', 5);
        $oldPasswords = static::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->skip($historyCount)
            ->pluck('id');

        if ($oldPasswords->isNotEmpty()) {
            static::whereIn('id', $oldPasswords)->delete();
        }
    }
}