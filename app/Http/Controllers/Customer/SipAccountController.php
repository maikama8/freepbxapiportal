<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\SipAccount;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class SipAccountController extends Controller
{
    /**
     * Display user's SIP accounts
     */
    public function index(): View
    {
        $user = Auth::user();
        $sipAccounts = $user->sipAccounts()->orderBy('is_primary', 'desc')->get();
        
        return view('customer.sip-accounts.index', compact('sipAccounts'));
    }

    /**
     * Show SIP account details
     */
    public function show(SipAccount $sipAccount): View
    {
        // Ensure user can only view their own SIP accounts
        if ($sipAccount->user_id !== Auth::id()) {
            abort(403);
        }

        return view('customer.sip-accounts.show', compact('sipAccount'));
    }

    /**
     * Show form to change SIP password
     */
    public function editPassword(SipAccount $sipAccount): View
    {
        // Ensure user can only edit their own SIP accounts
        if ($sipAccount->user_id !== Auth::id()) {
            abort(403);
        }

        return view('customer.sip-accounts.change-password', compact('sipAccount'));
    }

    /**
     * Update SIP password
     */
    public function updatePassword(Request $request, SipAccount $sipAccount): RedirectResponse
    {
        // Ensure user can only update their own SIP accounts
        if ($sipAccount->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        // Verify current password
        if ($request->current_password !== $sipAccount->sip_password) {
            return back()->withErrors(['current_password' => 'Current SIP password is incorrect.']);
        }

        $sipAccount->update(['sip_password' => $request->new_password]);

        return redirect()
            ->route('customer.sip-accounts.index')
            ->with('success', 'SIP password updated successfully.');
    }

    /**
     * Update SIP account settings (limited fields for customers)
     */
    public function updateSettings(Request $request, SipAccount $sipAccount): RedirectResponse
    {
        // Ensure user can only update their own SIP accounts
        if ($sipAccount->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'display_name' => 'required|string|max:255',
            'voicemail_enabled' => 'boolean',
            'voicemail_email' => 'nullable|email',
            'call_forward_enabled' => 'boolean',
            'call_forward_number' => 'nullable|string|max:20',
        ]);

        $sipAccount->update([
            'display_name' => $request->display_name,
            'voicemail_enabled' => $request->boolean('voicemail_enabled'),
            'voicemail_email' => $request->voicemail_email ?: Auth::user()->email,
            'call_forward_enabled' => $request->boolean('call_forward_enabled'),
            'call_forward_number' => $request->call_forward_number,
        ]);

        return redirect()
            ->route('customer.sip-accounts.index')
            ->with('success', 'SIP account settings updated successfully.');
    }
}