<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Editing reuses the create permission — there's no separate
        // "account.update" key in the MVP's fixed permission set (spec §3).
        return $this->user()->can('account.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Deliberately the raw route segment, not a resolved model: implicit
        // route-model binding would look the account up globally, and this
        // request is validated before the controller gets a chance to scope
        // it to the active company (spec §16).
        $accountId = $this->route('account');
        $companyId = $this->user()->activeCompany()->id;

        return [
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('accounts', 'code')->where('company_id', $companyId)->ignore($accountId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Account::TYPES)],
            'parent_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where('company_id', $companyId),
                Rule::notIn([$accountId]),
            ],
            'status' => ['required', Rule::in([Account::ACTIVE, Account::INACTIVE])],
        ];
    }
}
