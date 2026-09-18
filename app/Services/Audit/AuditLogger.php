<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;

class AuditLogger
{
    /**
     * Writes one append-only audit record (spec §15 / biz §28). Never
     * updates or deletes — every call is a new row.
     */
    public function log(
        Company $company,
        User $user,
        string $action,
        string $resourceType,
        int|string $resourceId,
        ?array $before = null,
        ?array $after = null,
    ): AuditLog {
        return AuditLog::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'before_data' => $before,
            'after_data' => $after,
        ]);
    }
}
