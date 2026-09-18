<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashInRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Cash In generates a journal, so it reuses journal.create rather
        // than a permission key spec's fixed set (§3 STEP 3) doesn't define.
        return $this->user()->can('journal.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()->activeCompany()->id;

        return [
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            // STEP 8's DoD ("inactive cash account cannot be used for new
            // transactions") is enforced right here, not just in the UI.
            'cash_account_id' => [
                'required',
                Rule::exists('cash_accounts', 'id')->where('company_id', $companyId)->where('status', 'ACTIVE'),
            ],
            'counter_account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where('company_id', $companyId),
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cash_account_id.exists' => 'Select an active cash account in this company.',
            'counter_account_id.exists' => 'Select an account in this company.',
            'amount.gt' => 'Amount must be greater than zero — negative or zero amounts are not allowed (biz §31).',
        ];
    }
}
