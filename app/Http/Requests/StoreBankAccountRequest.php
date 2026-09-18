<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\BankAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Same reasoning as cash accounts: this is account configuration,
        // not a day-to-day accounting operation, so it reuses account.create
        // rather than a new permission key spec never defines.
        return $this->user()->can('account.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()->activeCompany()->id;

        return [
            'bank_name' => ['required', 'string', 'max:255'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:64'],
            'account_id' => [
                'required',
                Rule::exists('accounts', 'id')
                    ->where('company_id', $companyId)
                    ->where('type', Account::ASSET),
            ],
            'status' => ['required', Rule::in([BankAccount::ACTIVE, BankAccount::INACTIVE])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.exists' => 'The linked account must be an ASSET account in this company (spec STEP 9).',
        ];
    }
}
