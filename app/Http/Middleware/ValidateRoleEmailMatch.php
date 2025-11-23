<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * ValidateRoleEmailMatch Middleware
 * 
 * Security Layer: Prevents role manipulation via direct database changes
 * 
 * How it works:
 * 1. When a user is created, their email is stored in 'authorized_email' field
 * 2. This field should NEVER change - it's locked to their original role
 * 3. On every request, we verify:
 *    - Current email matches authorized_email
 *    - Super Admin email is exactly 'superadmin@jadepayroll.com'
 * 
 * Attack Prevention:
 * - If someone changes role in database (e.g., HR Head -> Super Admin)
 * - But email doesn't match (hrhead.default@jadepayroll.com != superadmin@jadepayroll.com)
 * - User is immediately logged out with error message
 * 
 * Example Scenarios:
 * - ✅ Super Admin with superadmin@jadepayroll.com: ALLOWED
 * - ❌ HR Head changes role to Super Admin in DB: BLOCKED (email mismatch)
 * - ❌ Someone creates new user with superadmin@jadepayroll.com email: BLOCKED (unique constraint)
 * - ❌ Someone changes authorized_email in DB: BLOCKED (email won't match current email)
 */
class ValidateRoleEmailMatch
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user) {
            // Check if the user's current email matches their authorized email
            // authorized_email is set when the user is created and should never change
            if ($user->authorized_email && $user->email !== $user->authorized_email) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => 'Role manipulation detected! Your account has been flagged. Please contact the Super Administrator.']);
            }

            // Additional check: Verify role matches expected email patterns
            // Super Admin MUST be superadmin@jadepayroll.com
            if ($user->hasRole('Super Admin') && $user->email !== 'superadmin@jadepayroll.com') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => 'You manipulated the role! Please contact the Super Administrator.']);
            }
        }

        return $next($request);
    }
}
