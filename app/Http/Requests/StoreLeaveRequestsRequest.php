<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreLeaveRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
            'attachment' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx',
        ];
    }

    public function messages()
    {
        return [
            'start_date.required' => 'Start date is required',
            'start_date.date' => 'Start date must be a valid date',
            'end_date.required' => 'End date is required',
            'end_date.date' => 'End date must be a valid date',
            'end_date.after_or_equal' => 'End date must be after or equal to start date',
            'reason.required' => 'Reason is required',
            'reason.string' => 'Reason must be a string',
            'attachment.required' => 'Attachment is required',
            'attachment.file' => 'Attachment must be a file',
            'attachment.max' => 'Attachment size must not exceed 5MB',
            'attachment.mimes' => 'Attachment must be a pdf, jpg, jpeg, png, doc, or docx file',
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
