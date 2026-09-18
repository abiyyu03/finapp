<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    public const ASSET = 'ASSET';

    public const LIABILITY = 'LIABILITY';

    public const EQUITY = 'EQUITY';

    public const REVENUE = 'REVENUE';

    public const EXPENSE = 'EXPENSE';

    public const TYPES = [self::ASSET, self::LIABILITY, self::EQUITY, self::REVENUE, self::EXPENSE];

    public const ACTIVE = 'ACTIVE';

    public const INACTIVE = 'INACTIVE';

    protected $fillable = ['company_id', 'code', 'name', 'type', 'parent_id', 'status', 'is_system'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
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
     * @return BelongsTo<Account, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }
}
