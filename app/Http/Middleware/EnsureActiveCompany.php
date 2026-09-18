<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves and guards the active company for the current session (spec
 * STEP 2). Runs after `auth` on every company-scoped route.
 *
 * - No membership at all → blocked (nothing to isolate into yet).
 * - Exactly one company → selected automatically.
 * - Several companies and none chosen (or a stale/foreign one lingering in
 *   the session) → sent to the company picker instead of falling back to
 *   any company silently.
 */
class EnsureActiveCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $companyIds = $user->companies()->pluck('companies.id');

        if ($companyIds->isEmpty()) {
            abort(403, 'Your account is not a member of any company yet. Contact an administrator.');
        }

        $activeId = $request->session()->get('active_company_id');

        if (! $activeId || ! $companyIds->contains($activeId)) {
            if ($companyIds->count() > 1) {
                return redirect()->route('company.select');
            }

            $request->session()->put('active_company_id', $companyIds->first());
        }

        return $next($request);
    }
}
