<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanySwitchController extends Controller
{
    /**
     * Show the company picker (used when a user belongs to several
     * companies and none is active yet).
     */
    public function select(Request $request)
    {
        return view('select-company', [
            'companies' => $request->user()->companies,
        ]);
    }

    /**
     * Switch the active company.
     *
     * The target company is only ever resolved from the authenticated
     * user's own memberships (spec §16) — a company_id that isn't one of
     * theirs simply isn't reachable here, regardless of what's in the URL.
     */
    public function switch(Request $request, Company $company): RedirectResponse
    {
        abort_unless(
            $request->user()->companies()->whereKey($company->id)->exists(),
            403,
        );

        $request->session()->put('active_company_id', $company->id);

        return redirect()->route('dashboard');
    }
}
