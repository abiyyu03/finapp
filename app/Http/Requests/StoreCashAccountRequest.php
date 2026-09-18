<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\CashAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Cash accounts are financial account *configuration*, same as the
        // Chart of Accounts — reuses account.create rather than inventing a
        // permission key spec's fixed set (§3 STEP 3) doesn't define.
        return $this->user()->can('account.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()->activeCompany()->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'account_id' => [
                'required',
                Rule::exists('accounts', 'id')
                    ->where('company_id', $companyId)
                    ->where('type', Account::ASSET),
            ],
            'status' => ['required', Rule::in([CashAccount::ACTIVE, CashAccount::INACTIVE])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.exists' => 'The linked account must be an ASSET account in this company (spec STEP 8).',
        ];
    }
}
