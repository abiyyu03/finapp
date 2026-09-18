<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    public const ACTIVE = 'ACTIVE';

    public const INACTIVE = 'INACTIVE';

    protected $fillable = ['company_id', 'bank_name', 'account_name', 'account_number', 'account_id', 'status'];

    /**
     * Never serialize the raw number by default (spec STEP 9: "do not
     * expose full account number unnecessarily"). Views that genuinely
     * need it (the edit form) read $account_number directly — this only
     * guards array/JSON output and debug dumps.
     */
    protected $hidden = ['account_number'];

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
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    /**
     * Last 4 digits only — this is what every screen except the edit form
     * should display.
     */
    public function maskedAccountNumber(): string
    {
        $number = (string) $this->account_number;

        return $number === ''
            ? ''
            : str_repeat('•', max(strlen($number) - 4, 0)).substr($number, -4);
    }
}
