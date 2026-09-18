<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    /**
     * Read-only, on purpose — there is no update or delete route anywhere
     * in this app for AuditLog (spec §15: "cannot be modified/deleted by
     * normal users"). The only writer is AuditLogger::log().
     */
    public function index(Request $request)
    {
        Gate::authorize('audit_log.view');

        $logs = $request->user()->activeCompany()
            ->auditLogs()
            ->with('user')
            ->latest('created_at')
            ->latest('id')
            ->paginate(30);

        return view('audit-logs.index', ['logs' => $logs]);
    }
}
