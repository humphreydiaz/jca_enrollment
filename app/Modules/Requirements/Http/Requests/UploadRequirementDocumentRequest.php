<?php

namespace App\Modules\Requirements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UploadRequirementDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $decision = Gate::forUser($this->user())->inspect('upload', $this->route('requirement'));
        abort_if($decision->denied(), 404);

        return true;
    }

    public function rules(): array
    {
        return [
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('document');
            if ($file && ! in_array(strtolower($file->getClientOriginalExtension()), ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                $validator->errors()->add('document', 'Upload a PDF, JPG, JPEG, or PNG file.');
            }
        });
    }
}
