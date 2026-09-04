<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     */
    public function rules(): array
    {
        $isTrade = in_array($this->input('type'), [
            TransactionType::Buy->value,
            TransactionType::Sell->value,
        ]);

        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'type' => ['required', Rule::enum(TransactionType::class)],

            'amount' => [Rule::requiredIf(!$isTrade), 'numeric', 'gt:0'],

            'ticker' => [Rule::requiredIf($isTrade), 'string', 'max:20'],
            'quantity' => [Rule::requiredIf($isTrade), 'integer', 'gt:0'],
            'price' => [Rule::requiredIf($isTrade), 'numeric', 'gt:0'],
        ];
    }
}
