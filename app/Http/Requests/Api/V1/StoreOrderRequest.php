<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * The endpoint is public: customers order without an account.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'business_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:25', app(PhoneNumber::class)],
            'message' => ['required', 'string', 'min:5', 'max:3000'],

            // Lifecycle fields belong to the administrator only.
            'status' => ['prohibited'],
            'confirmed_at' => ['prohibited'],
            'cancelled_at' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Il campo :attribute è obbligatorio.',
            'string' => 'Il campo :attribute deve essere un testo.',
            'max' => 'Il campo :attribute non può superare :max caratteri.',
            'min' => 'Il campo :attribute deve contenere almeno :min caratteri.',
            'prohibited' => 'Il campo :attribute non può essere impostato.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'nome',
            'last_name' => 'cognome',
            'business_name' => 'attività',
            'phone' => 'telefono',
            'message' => 'richiesta',
            'status' => 'stato',
            'confirmed_at' => 'data di conferma',
            'cancelled_at' => 'data di annullamento',
        ];
    }

    /**
     * Trim surrounding whitespace so "   " counts as an empty field.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->only(['first_name', 'last_name', 'business_name', 'phone', 'message']))
            ->map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value)
            ->all());
    }
}
