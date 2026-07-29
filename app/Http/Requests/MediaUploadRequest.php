<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MediaUploadRequest extends FormRequest
{
    /**
     * Allowed MIME types and their corresponding max sizes in KB.
     */
    public const ALLOWED_MIMES = [
        'image/jpeg' => 5120,   // 5 MB
        'image/png' => 5120,    // 5 MB
        'image/gif' => 3072,    // 3 MB
        'image/webp' => 3072,   // 3 MB
        'image/svg+xml' => 1024, // 1 MB
        'audio/mpeg' => 10240,  // 10 MB
        'audio/wav' => 10240,   // 10 MB
        'audio/ogg' => 8192,    // 8 MB
        'audio/mp4' => 8192,    // 8 MB
        'audio/x-m4a' => 8192,  // 8 MB
    ];

    public function authorize(): bool
    {
        // Authorization is handled by the policy on the controller action.
        return true;
    }

    public function rules(): array
    {
        $maxKb = max(self::ALLOWED_MIMES);

        return [
            'file' => [
                'required',
                'file',
                'max:'.$maxKb,
                'mimetypes:'.implode(',', array_keys(self::ALLOWED_MIMES)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Vui lòng chọn một file để tải lên.',
            'file.file' => 'Tải lên không hợp lệ.',
            'file.max' => 'File không được vượt quá dung lượng cho phép.',
            'file.mimetypes' => 'Định dạng file không được hỗ trợ. Chỉ chấp nhận ảnh (JPEG, PNG, GIF, WebP, SVG) và audio (MP3, WAV, OGG, MP4 audio, M4A).',
        ];
    }

    /**
     * Get the MIME-type-specific max size for the uploaded file.
     */
    public function maxSizeForFile(): int
    {
        $mime = $this->file('file')?->getMimeType();

        return self::ALLOWED_MIMES[$mime] ?? 1024;
    }

    /**
     * Return a sanitized, unique filename for storage.
     */
    public function sanitizedFilename(): string
    {
        $file = $this->file('file');

        return md5($file->getClientOriginalName().microtime()).'.'.$file->getClientOriginalExtension();
    }
}
