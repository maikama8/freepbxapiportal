<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SipAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SipAccountController extends Controller
{
    /**
     * Display SIP accounts for a user
     */
    public function index(User $user): View
    {
        $sipAccounts = $user->sipAccounts()->orderBy('is_primary', 'desc')->get();
        
        return view('admin.sip-accounts.index', compact('user', 'sipAccounts'));
    }

    /**
     * Show form to create new SIP account
     */
    public function create(User $user): View
    {
        return view('admin.sip-accounts.create', compact('user'));
    }

    /**
     * Store new SIP account
     */
    public function store(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'display_name' => 'required|string|max:255',
            'sip_username' => 'nullable|string|unique:sip_accounts,sip_username',
            'sip_password' => 'nullable|string|min:8',
            'sip_context' => 'required|string|max:50',
            'status' => 'required|in:active,inactive,suspended',
            'is_primary' => 'boolean',
            'voicemail_enabled' => 'boolean',
            'voicemail_email' => 'nullable|email',
            'call_forward_enabled' => 'boolean',
            'call_forward_number' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Generate SIP username if not provided
        $sipUsername = $request->sip_username ?: SipAccount::getNextSipUsername();
        
        // Generate SIP password if not provided
        $sipPassword = $request->sip_password ?: SipAccount::generateSipPassword();

        // If this is set as primary, unset other primary accounts
        if ($request->boolean('is_primary')) {
            $user->sipAccounts()->update(['is_primary' => false]);
        }

        $sipAccount = $user->sipAccounts()->create([
            'sip_username' => $sipUsername,
            'sip_password' => $sipPassword,
            'sip_context' => $request->sip_context,
            'display_name' => $request->display_name,
            'status' => $request->status,
            'is_primary' => $request->boolean('is_primary'),
            'voicemail_enabled' => $request->boolean('voicemail_enabled'),
            'voicemail_email' => $request->voicemail_email ?: $user->email,
            'call_forward_enabled' => $request->boolean('call_forward_enabled'),
            'call_forward_number' => $request->call_forward_number,
            'notes' => $request->notes,
        ]);

        return redirect()
            ->route('admin.customers.sip-accounts.index', $user)
            ->with('success', 'SIP account created successfully.');
    }

    /**
     * Show form to edit SIP account
     */
    public function edit(User $user, SipAccount $sipAccount): View
    {
        return view('admin.sip-accounts.edit', compact('user', 'sipAccount'));
    }

    /**
     * Update SIP account
     */
    public function update(Request $request, User $user, SipAccount $sipAccount): RedirectResponse
    {
        $request->validate([
            'display_name' => 'required|string|max:255',
            'sip_password' => 'nullable|string|min:8',
            'sip_context' => 'required|string|max:50',
            'status' => 'required|in:active,inactive,suspended',
            'is_primary' => 'boolean',
            'voicemail_enabled' => 'boolean',
            'voicemail_email' => 'nullable|email',
            'call_forward_enabled' => 'boolean',
            'call_forward_number' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:1000',
        ]);

        // If this is set as primary, unset other primary accounts
        if ($request->boolean('is_primary') && !$sipAccount->is_primary) {
            $user->sipAccounts()->where('id', '!=', $sipAccount->id)->update(['is_primary' => false]);
        }

        $updateData = [
            'display_name' => $request->display_name,
            'sip_context' => $request->sip_context,
            'status' => $request->status,
            'is_primary' => $request->boolean('is_primary'),
            'voicemail_enabled' => $request->boolean('voicemail_enabled'),
            'voicemail_email' => $request->voicemail_email ?: $user->email,
            'call_forward_enabled' => $request->boolean('call_forward_enabled'),
            'call_forward_number' => $request->call_forward_number,
            'notes' => $request->notes,
        ];

        // Only update password if provided
        if ($request->filled('sip_password')) {
            $updateData['sip_password'] = $request->sip_password;
        }

        $sipAccount->update($updateData);

        return redirect()
            ->route('admin.customers.sip-accounts.index', $user)
            ->with('success', 'SIP account updated successfully.');
    }

    /**
     * Delete SIP account
     */
    public function destroy(User $user, SipAccount $sipAccount): RedirectResponse
    {
        // Prevent deletion of primary account if it's the only one
        if ($sipAccount->is_primary && $user->sipAccounts()->count() === 1) {
            return redirect()
                ->route('admin.customers.sip-accounts.index', $user)
                ->with('error', 'Cannot delete the only SIP account for this user.');
        }

        $sipAccount->delete();

        // If we deleted the primary account, make another one primary
        if ($sipAccount->is_primary) {
            $user->sipAccounts()->first()?->update(['is_primary' => true]);
        }

        return redirect()
            ->route('admin.customers.sip-accounts.index', $user)
            ->with('success', 'SIP account deleted successfully.');
    }

    /**
     * Reset SIP password
     */
    public function resetPassword(User $user, SipAccount $sipAccount): RedirectResponse
    {
        $newPassword = SipAccount::generateSipPassword();
        $sipAccount->update(['sip_password' => $newPassword]);

        return redirect()
            ->route('admin.customers.sip-accounts.index', $user)
            ->with('success', "SIP password reset successfully. New password: {$newPassword}");
    }
}