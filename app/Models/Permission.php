<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    /**
     * The MVP's fixed permission keys (spec §3 STEP 3, extended with
     * user.manage/role.manage so /users and /roles are guarded by the same
     * mechanism as everything else instead of a bespoke role-name check).
     */
    public const ACCOUNT_VIEW = 'account.view';

    public const ACCOUNT_CREATE = 'account.create';

    public const JOURNAL_VIEW = 'journal.view';

    public const JOURNAL_CREATE = 'journal.create';

    public const JOURNAL_POST = 'journal.post';

    public const REPORT_VIEW = 'report.view';

    public const AUDIT_LOG_VIEW = 'audit_log.view';

    public const USER_MANAGE = 'user.manage';

    public const ROLE_MANAGE = 'role.manage';

    protected $fillable = ['key'];

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
