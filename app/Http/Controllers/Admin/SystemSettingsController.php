<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SystemSettingsController extends Controller
{
    // Middleware is handled in routes, not needed in constructor for Laravel 11

    /**
     * Display system settings
     */
    public function index()
    {
        $settings = SystemSetting::orderBy('group')->orderBy('key')->get()->groupBy('group');
        
        return view('admin.settings.index', compact('settings'));
    }

    /**
     * Update system settings
     */
    public function update(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|string|max:1000'
        ]);

        foreach ($request->settings as $key => $value) {
            $setting = SystemSetting::where('key', $key)->first();
            
            if ($setting) {
                $setting->update(['value' => $value]);
                
                // Clear cache for this setting
                Cache::forget("system_setting_{$key}");
            }
        }

        return redirect()->back()->with('success', 'System settings updated successfully.');
    }

    /**
     * Test FreePBX connection
     */
    public function testFreepbxConnection()
    {
        try {
            $freepbxService = app(\App\Services\FreePBX\FreePBXApiClient::class);
            $result = $freepbxService->testConnection();
            
            return response()->json([
                'success' => true,
                'message' => 'FreePBX connection successful',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'FreePBX connection failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get SIP server status
     */
    public function getSipServerStatus()
    {
        try {
            $host = SystemSetting::get('sip_server_host');
            $port = SystemSetting::get('sip_server_port', 5060);
            
            // Simple socket connection test
            $connection = @fsockopen($host, $port, $errno, $errstr, 5);
            
            if ($connection) {
                fclose($connection);
                return response()->json([
                    'success' => true,
                    'message' => 'SIP server is reachable',
                    'status' => 'online'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => "SIP server unreachable: $errstr ($errno)",
                    'status' => 'offline'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking SIP server: ' . $e->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }

    /**
     * Reset settings to default
     */
    public function resetToDefaults(Request $request)
    {
        $request->validate([
            'group' => 'required|string'
        ]);

        $group = $request->group;
        
        // Delete existing settings for this group
        SystemSetting::where('group', $group)->delete();
        
        // Clear cache
        Cache::flush();
        
        // Re-seed defaults for this group
        $seeder = new \Database\Seeders\SystemSettingsSeeder();
        $seeder->run();
        
        return redirect()->back()->with('success', "Settings for group '{$group}' have been reset to defaults.");
    }
}