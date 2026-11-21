<?php

namespace App\Http\Requests\ExamDocuments;

use App\Models\ExamPeriod;
use App\Services\Documents\DocxPackageValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StoreExamDocumentRequest extends FormRequest
{
    /**
     * @var array<int, array{mime_type: string, size_bytes: int, checksum_sha256: string}>
     */
    private array $validatedPackages = [];

    public function authorize(): bool
    {
        return $this->user()?->is_active ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxBatch = (int) config('simsoal.max_batch_files', 20);
        $maxKb = (int) config('simsoal.max_upload_mb', 30) * 1024;

        return [
            'exam_period_id' => ['required', 'integer', 'exists:exam_periods,id'],
            'items' => ['required', 'array', 'min:1', "max:{$maxBatch}"],
            'items.*.file' => ['required', 'file', "max:{$maxKb}"],
            'items.*.subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'items.*.school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'items.*.teacher_name' => ['required', 'string', 'max:150'],
            'items.*.exam_date' => ['nullable', 'date'],
            'items.*.duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'items.*.document_title' => ['nullable', 'string', 'max:200'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'exam_period_id.required' => 'Periode ujian wajib dipilih.',
            'exam_period_id.exists' => 'Periode ujian tidak ditemukan.',
            'items.required' => 'Minimal satu file dokumen DOCX harus diunggah.',
            'items.min' => 'Minimal satu file dokumen DOCX harus diunggah.',
            'items.*.file.required' => 'File dokumen wajib disertakan.',
            'items.*.file.max' => 'Ukuran file melebihi batas maksimal yang diizinkan.',
            'items.*.teacher_name.required' => 'Nama guru penyusun soal wajib diisi pada setiap dokumen.',
            'items.*.duration_minutes.min' => 'Durasi ujian minimal 1 menit.',
            'items.*.duration_minutes.max' => 'Durasi ujian maksimal 600 menit.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $periodId = $this->integer('exam_period_id');
            if ($periodId > 0) {
                $period = ExamPeriod::query()->find($periodId);
                if ($period && ! $period->acceptsUploads()) {
                    $validator->errors()->add(
                        'exam_period_id',
                        'Periode ujian ini sudah diarsipkan dan tidak dapat menerima unggahan dokumen baru.'
                    );
                }
            }

            $packageValidator = app(DocxPackageValidator::class);
            $items = $this->input('items', []);

            if (! is_array($items)) {
                return;
            }

            foreach (array_keys($items) as $index) {
                $uploadedFile = $this->file("items.{$index}.file");
                if (! $uploadedFile instanceof UploadedFile) {
                    continue;
                }

                $result = $packageValidator->validateUploadedDocx($uploadedFile);
                if (! $result['valid']) {
                    $validator->errors()->add("items.{$index}.file", (string) $result['error']);
                } else {
                    $this->validatedPackages[$index] = [
                        'mime_type' => $result['mime_type'],
                        'size_bytes' => $result['size_bytes'],
                        'checksum_sha256' => $result['checksum_sha256'],
                    ];
                }
            }
        });
    }

    /**
     * @return array<int, array{mime_type: string, size_bytes: int, checksum_sha256: string}>
     */
    public function validatedPackages(): array
    {
        return $this->validatedPackages;
    }
}