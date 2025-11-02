<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Log;

class EncryptionService
{
    /**
     * Encrypt sensitive data
     */
    public static function encrypt(string $data): ?string
    {
        try {
            return Crypt::encryptString($data);
        } catch (\Exception $e) {
            Log::error('Encryption failed', [
                'error' => $e->getMessage(),
                'data_length' => strlen($data)
            ]);
            return null;
        }
    }

    /**
     * Decrypt sensitive data
     */
    public static function decrypt(string $encryptedData): ?string
    {
        try {
            return Crypt::decryptString($encryptedData);
        } catch (DecryptException $e) {
            Log::error('Decryption failed', [
                'error' => $e->getMessage(),
                'encrypted_data_length' => strlen($encryptedData)
            ]);
            return null;
        }
    }

    /**
     * Hash sensitive data (one-way)
     */
    public static function hash(string $data, string $algorithm = 'sha256'): string
    {
        return hash($algorithm, $data);
    }

    /**
     * Generate secure random string
     */
    public static function generateSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Encrypt array data
     */
    public static function encryptArray(array $data): ?string
    {
        try {
            return static::encrypt(json_encode($data));
        } catch (\Exception $e) {
            Log::error('Array encryption failed', [
                'error' => $e->getMessage(),
                'array_keys' => array_keys($data)
            ]);
            return null;
        }
    }

    /**
     * Decrypt array data
     */
    public static function decryptArray(string $encryptedData): ?array
    {
        try {
            $decrypted = static::decrypt($encryptedData);
            return $decrypted ? json_decode($decrypted, true) : null;
        } catch (\Exception $e) {
            Log::error('Array decryption failed', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Mask sensitive data for display
     */
    public static function maskSensitiveData(string $data, int $visibleChars = 4): string
    {
        $length = strlen($data);
        
        if ($length <= $visibleChars) {
            return str_repeat('*', $length);
        }
        
        $masked = str_repeat('*', $length - $visibleChars);
        return $masked . substr($data, -$visibleChars);
    }

    /**
     * Validate encrypted data integrity
     */
    public static function validateIntegrity(string $encryptedData): bool
    {
        try {
            static::decrypt($encryptedData);
            return true;
        } catch (DecryptException $e) {
            return false;
        }
    }

    /**
     * Securely compare two strings (timing attack safe)
     */
    public static function secureCompare(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }

    /**
     * Generate cryptographically secure password
     */
    public static function generateSecurePassword(int $length = 16): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        
        $password = '';
        
        // Ensure at least one character from each set
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];
        
        // Fill the rest randomly
        $allChars = $uppercase . $lowercase . $numbers . $symbols;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }
        
        // Shuffle the password
        return str_shuffle($password);
    }
}