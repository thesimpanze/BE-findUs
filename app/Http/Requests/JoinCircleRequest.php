<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JoinCircleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Endpoint sudah dilindungi middleware sanctum
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'referal_code' => ['required', 'string', 'size:5'],
        ];
    }
    
    
    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'referal_code.required' => 'Kode referal wajib diisi.',
            'referal_code.size'     => 'Kode referal harus tepat 5 karakter.',
        ];
    }
}
