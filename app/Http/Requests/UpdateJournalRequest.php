<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Whether the journal itself is still DRAFT (spec §13: posted is
        // immutable) is checked in the controller, alongside the company
        // scoping lookup — not here, where there's no model in hand yet.
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
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where('company_id', $companyId),
            ],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('lines', []) as $index => $line) {
                $debit = $line['debit'] ?? 0;
                $credit = $line['credit'] ?? 0;

                if ($debit > 0 && $credit > 0) {
                    $validator->errors()->add("lines.{$index}.debit", 'A line cannot have both a debit and a credit amount (spec §12).');
                }

                if (! ($debit > 0) && ! ($credit > 0)) {
                    $validator->errors()->add("lines.{$index}.debit", 'Enter a debit or a credit amount.');
                }
            }
        });
    }
}
