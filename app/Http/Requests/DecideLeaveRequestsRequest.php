<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DecideLeaveRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:approved,rejected',
            'decision_note' => 'nullable|string',
        ];
    }

    public function messages()
    {
        return [
            'status.required' => 'Status is required',
            'status.in' => 'Status must be approved or rejected',
            'decision_note.string' => 'Decision note must be a string',
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
