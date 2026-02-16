<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreLeaveEntitlementsRequest extends FormRequest
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
            'user_id' => 'required|exists:users,id',
            'year' => 'required|integer|digits:4|min:1900|max:2100',
            'quota_days' => 'required|integer|min:0',
            'created_by' => 'nullable|exists:users,id',
        ];
    }

    public function messages()
    {
        return [
            'user_id.required' => 'User is required',
            'user_id.exists' => 'User not found',
            'year.required' => 'Year is required',
            'year.numeric' => 'Year must be a number',
            'quota_days.required' => 'Quota days is required',
            'quota_days.numeric' => 'Quota days must be a number',
            'created_by.required' => 'Created by is required',
            'created_by.exists' => 'Created by not found',
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
