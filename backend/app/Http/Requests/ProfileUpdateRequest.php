<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required','string','lowercase','email','max:255', Rule::unique(User::class)->ignore($this->user()->id),],
            'role' => ['sometimes', 'required', 'in:admin,lab_technician'],
            'phone_number' => ['nullable', 'string', 'max:25'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'profile_pic' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ];
    }
}
