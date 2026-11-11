<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'expiry_warning_days' => ['required', 'integer', 'min:1', 'max:365'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expiry_warning_days.required' => 'Batas hari peringatan kedaluwarsa wajib diisi.',
            'expiry_warning_days.integer' => 'Batas hari peringatan kedaluwarsa harus berupa bilangan bulat.',
            'expiry_warning_days.min' => 'Batas hari peringatan kedaluwarsa minimal 1 hari.',
            'expiry_warning_days.max' => 'Batas hari peringatan kedaluwarsa maksimal 365 hari.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'expiry_warning_days' => 'batas hari peringatan kedaluwarsa',
        ];
    }
}
