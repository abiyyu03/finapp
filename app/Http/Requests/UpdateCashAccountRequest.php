<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\CashAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCashAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
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
