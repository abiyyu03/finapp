<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    const UPDATED_AT = null;

    public const LOGIN = 'LOGIN';

    public const CREATE = 'CREATE';

    public const UPDATE = 'UPDATE';

    public const DEACTIVATE = 'DEACTIVATE';

    public const POST = 'POST';

    protected $fillable = [
        'company_id', 'user_id', 'action', 'resource_type', 'resource_id', 'before_data', 'after_data',
    ];

    protected function casts(): array
    {
        return [
            'before_data' => 'array',
            'after_data' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
