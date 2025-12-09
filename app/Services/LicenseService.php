<?php

namespace App\Services;

use App\Models\SystemLicense;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    public static function generateServerFingerprint()
    {
        $data = [
            'server_name' => gethostname(),
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
            'php_version' => PHP_VERSION,
        ];

        return hash('sha256', serialize($data));
    }

    public static function validateLicense()
    {
        $license = SystemLicense::current();

        if (!$license) {
            return ['valid' => false, 'reason' => 'No active license found'];
        }

        if ($license->isExpired()) {
            return ['valid' => false, 'reason' => 'License expired'];
        }

        // Check server fingerprint (can be disabled for development)
        if (config('app.env') === 'production') {
            if ($license->server_fingerprint !== self::generateServerFingerprint()) {
                return ['valid' => false, 'reason' => 'License not valid for this server'];
            }
        }

        // Check employee limit
        if ($license->hasReachedEmployeeLimit()) {
            return ['valid' => false, 'reason' => 'Employee limit exceeded'];
        }

        return ['valid' => true, 'license' => $license];
    }

    public static function activateLicense($licenseKey)
    {
        // Clean the license key (keep dashes for LIC- format)
        $cleanKey = trim($licenseKey);

        // Check if it's the new LIC-XXXXXXXX-XXXXXXXX format
        if (preg_match('/^LIC-[A-F0-9]{8}-[A-F0-9]{8}$/', $cleanKey)) {
            // New format - must exist in database
            $existingLicense = SystemLicense::where('license_key', $cleanKey)->first();

            if (!$existingLicense) {
                return ['success' => false, 'message' => 'Invalid license key. License not found in system.'];
            }

            // Check if already activated
            if ($existingLicense->is_active) {
                return ['success' => false, 'message' => 'This license key has already been activated. Contact your system administrator if you need assistance.'];
            }

            // Deactivate other licenses
            SystemLicense::where('is_active', true)->update(['is_active' => false]);

            // Activate this license
            $existingLicense->update([
                'server_fingerprint' => self::generateServerFingerprint(),
                'activated_at' => Carbon::now(),
                'countdown_started_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addDays($existingLicense->plan_info['duration_days'] ?? 30),
                'is_active' => true,
                'system_info' => [
                    'activated_by' => Auth::check() ? Auth::user()->email : 'system',
                    'server_info' => $_SERVER['HTTP_HOST'] ?? 'localhost',
                    'activation_ip' => request()->ip(),
                ]
            ]);

            return ['success' => true, 'license' => $existingLicense];
        }

        // Old base64 format - try to decode
        $cleanKey = preg_replace('/[^A-Za-z0-9+\/=.]/', '', $licenseKey);
        $decoded = self::decodeLicenseKey($cleanKey);

        if (!$decoded) {
            return ['success' => false, 'message' => 'Invalid license key format'];
        }

        // Note: We don't check expiry during activation since the license starts counting down from activation

        // Check if license key already exists
        if (SystemLicense::where('license_key', $cleanKey)->exists()) {
            return ['success' => false, 'message' => 'This license key has already been activated. Contact your system administrator if you need assistance.'];
        }

        // Deactivate existing licenses
        SystemLicense::where('is_active', true)->update(['is_active' => false]);

        // Create new license with plan information embedded
        $license = SystemLicense::create([
            'license_key' => $cleanKey,
            'server_fingerprint' => self::generateServerFingerprint(),
            'plan_info' => [
                'max_employees' => $decoded['max_employees'] ?? 100,
                'price' => $decoded['price'] ?? 0,
                'duration_days' => $decoded['duration_days'] ?? 30,
                'currency' => $decoded['currency'] ?? 'PHP',
                'customer' => $decoded['customer'] ?? null,
                'features' => $decoded['features'] ?? [],
            ],
            'activated_at' => Carbon::now(),
            'countdown_started_at' => Carbon::now(), // Start countdown immediately
            'expires_at' => Carbon::now()->addDays($decoded['duration_days'] ?? 30),
            'is_active' => true,
            'system_info' => [
                'activated_by' => Auth::check() ? Auth::user()->email : 'system',
                'server_info' => $_SERVER['HTTP_HOST'] ?? 'localhost',
                'license_data' => $decoded,
                'activation_ip' => request()->ip(),
            ]
        ]);

        return ['success' => true, 'license' => $license, 'data' => $decoded];
    }

    private static function decodeLicenseKey($licenseKey)
    {
        try {
            $parts = explode('.', $licenseKey);
            if (count($parts) !== 2) {
                return false;
            }

            $payloadEncoded = $parts[0];
            $signature = $parts[1];

            // Verify signature - check both new short format (16 chars) and old format (64 chars)
            $secret = config('app.license_secret', config('app.key'));
            $expectedSignature = hash_hmac('sha256', $payloadEncoded, $secret);

            // Support both new short signature (16 chars) and old full signature (64 chars)
            $isValidSignature = hash_equals($signature, $expectedSignature) ||
                hash_equals($signature, substr($expectedSignature, 0, 16));

            if (!$isValidSignature) {
                return false;
            }

            // Decode payload
            $payload = base64_decode($payloadEncoded);
            $decoded = json_decode($payload, true);
            if (!$decoded) {
                return false;
            }

            // Handle both compact format (new) and full format (old)
            if (isset($decoded['e'])) {
                // New compact format
                return [
                    'max_employees' => $decoded['e'],
                    'price' => $decoded['p'],
                    'duration_days' => $decoded['d'],
                    'issued_at' => $decoded['t'],
                    'customer' => $decoded['c'] ?? 'License Holder', // actual customer name
                    'currency' => 'PHP',
                    'features' => [
                        'payroll_management',
                        'employee_management',
                        'time_tracking',
                        'reports',
                        'email_notifications'
                    ],
                    'version' => '2.1'
                ];
            } else {
                // Old full format - validate required fields
                if (!isset($decoded['max_employees']) || !isset($decoded['duration_days'])) {
                    return false;
                }
                return $decoded;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function hasFeature($feature)
    {
        $license = SystemLicense::current();

        if (!$license || !$license->plan_info) {
            return false;
        }

        $features = $license->plan_info['features'] ?? [];

        // If features is empty, assume basic features are available
        if (empty($features)) {
            return true;
        }

        return in_array($feature, $features);
    }

    public static function getLicenseInfo($licenseKey)
    {
        $cleaned = preg_replace('/[^A-Za-z0-9+\/=.]/', '', $licenseKey);
        return self::decodeLicenseKey($cleaned);
    }

    /**
     * Validate if a license key exists in the system licenses table
     */
    public static function isValidLicenseKey($licenseKey)
    {
        if (empty($licenseKey)) {
            return false;
        }

        // Clean the license key - keep dashes for LIC- format
        $cleaned = trim($licenseKey);

        // For LIC-XXXXXXXX-XXXXXXXX format, don't remove dashes
        if (preg_match('/^LIC-[A-F0-9]{8}-[A-F0-9]{8}$/', $cleaned)) {
            return SystemLicense::where('license_key', $cleaned)->exists();
        }

        // For old base64 format, remove special characters except base64 chars
        $cleaned = preg_replace('/[^A-Za-z0-9+\/=.]/', '', $licenseKey);

        // Check if it exists in system_licenses table
        return SystemLicense::where('license_key', $cleaned)->exists();
    }
}
