<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateLeaveEntitlementsRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quota_days' => 'required|numeric',
            'carried_forward_days' => 'required|numeric',
        ];
    }

    public function messages()
    {
        return [
            'quota_days.required' => 'Quota days is required',
            'quota_days.numeric' => 'Quota days must be a number',
            'carried_forward_days.required' => 'Carried forward days is required',
            'carried_forward_days.numeric' => 'Carried forward days must be a number',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'meta' => [
                'code' => 400,
                'status' => 'error',
                'message' => 'Validation Error'
            ],
            'data' => $validator->errors()
        ], 400));
    }
}
