<?php

namespace App\Http\Controllers;

use App\Enums\DocumentFileRole;
use App\Enums\DocumentStatus;
use App\Enums\ExamPeriodStatus;
use App\Http\Requests\ExamDocuments\StoreExamDocumentRequest;
use App\Http\Requests\ExamDocuments\UpdateExamDocumentRequest;
use App\Models\DocumentFile;
use App\Models\ExamDocument;
use App\Models\ExamPeriod;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\Storage\PrivateDocumentStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExamDocumentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ExamDocument::class);

        $search = trim((string) $request->string('search', ''));
        $periodId = $request->integer('period_id');
        $subjectId = $request->integer('subject_id');
        $classId = $request->integer('class_id');
        $status = (string) $request->string('status', '');

        $documents = ExamDocument::query()
            ->with([
                'examPeriod:id,name,academic_year',
                'subject:id,name,code',
                'schoolClass:id,name',
                'uploader:id,name',
                'sourceFile',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('document_title', 'like', "%{$search}%")
                        ->orWhere('teacher_name', 'like', "%{$search}%")
                        ->orWhereHas('sourceFile', fn ($fq) => $fq->where('original_filename', 'like', "%{$search}%"));
                });
            })
            ->when($periodId > 0, fn ($q) => $q->where('exam_period_id', $periodId))
            ->when($subjectId > 0, fn ($q) => $q->where('subject_id', $subjectId))
            ->when($classId > 0, fn ($q) => $q->where('school_class_id', $classId))
            ->when(in_array($status, DocumentStatus::values(), true), fn ($q) => $q->where('current_status', $status))
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString()
            ->through(function (ExamDocument $doc) {
                $source = $doc->sourceFile;

                return [
                    'id' => $doc->id,
                    'document_title' => $doc->document_title ?: ($source?->original_filename ?? 'Tanpa Judul'),
                    'teacher_name' => $doc->teacher_name,
                    'period_name' => $doc->examPeriod?->name ?? '-',
                    'academic_year' => $doc->examPeriod?->academic_year ?? '-',
                    'subject_name' => $doc->subject?->name ?? 'Belum ditentukan',
                    'class_name' => $doc->schoolClass?->name ?? 'Belum ditentukan',
                    'current_status' => $doc->current_status->value,
                    'status_label' => $doc->current_status->label(),
                    'source_file' => $source ? [
                        'id' => $source->id,
                        'original_filename' => $source->original_filename,
                        'human_size' => $source->humanSize(),
                        'download_url' => route('document-files.download', $source->id),
                    ] : null,
                    'created_at' => $doc->created_at?->format('d M Y H:i'),
                ];
            });

        return Inertia::render('ExamDocuments/Index', [
            'documents' => $documents,
            'filters' => [
                'search' => $search,
                'period_id' => $periodId ?: '',
                'subject_id' => $subjectId ?: '',
                'class_id' => $classId ?: '',
                'status' => $status,
            ],
            'periods' => ExamPeriod::query()
                ->orderByDesc('created_at')
                ->get(['id', 'name', 'academic_year', 'status']),
            'subjects' => Subject::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'schoolClasses' => SchoolClass::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'statuses' => collect(DocumentStatus::cases())->map(fn (DocumentStatus $st) => [
                'value' => $st->value,
                'label' => $st->label(),
            ])->values(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', ExamDocument::class);

        $availablePeriods = ExamPeriod::query()
            ->whereIn('status', [ExamPeriodStatus::ACTIVE, ExamPeriodStatus::DRAFT])
            ->orderByRaw("CASE status WHEN 'ACTIVE' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'academic_year', 'status']);

        return Inertia::render('ExamDocuments/Create', [
            'periods' => $availablePeriods,
            'subjects' => Subject::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'schoolClasses' => SchoolClass::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'level']),
            'limits' => [
                'max_upload_mb' => (int) config('simsoal.max_upload_mb', 30),
                'max_batch_files' => (int) config('simsoal.max_batch_files', 20),
            ],
        ]);
    }

    public function store(
        StoreExamDocumentRequest $request,
        PrivateDocumentStorageService $storageService
    ): RedirectResponse {
        Gate::authorize('create', ExamDocument::class);

        $validated = $request->validated();
        $packages = $request->validatedPackages();
        $period = ExamPeriod::query()->findOrFail($validated['exam_period_id']);

        $itemsPayload = [];
        foreach ($validated['items'] as $index => $itemData) {
            $itemsPayload[] = [
                'file' => $request->file("items.{$index}.file"),
                'validation' => $packages[$index],
                'subject_id' => $itemData['subject_id'] ?? null,
                'school_class_id' => $itemData['school_class_id'] ?? null,
                'teacher_name' => $itemData['teacher_name'],
                'exam_date' => $itemData['exam_date'] ?? null,
                'duration_minutes' => $itemData['duration_minutes'] ?? null,
                'document_title' => $itemData['document_title'] ?? null,
                'notes' => $itemData['notes'] ?? null,
            ];
        }

        $result = $storageService->storeBatchSources($period, $itemsPayload, $request->user());
        $count = count($result['documents']);

        $redirect = redirect()
            ->route('exam-documents.index')
            ->with('success', "{$count} dokumen soal DOCX berhasil diunggah ke penyimpanan privat.");

        if ($result['duplicate_count'] > 0) {
            $redirect->with(
                'warning',
                "{$result['duplicate_count']} file terdeteksi memiliki checksum yang sama dengan dokumen yang sudah ada pada periode ini."
            );
        }

        return $redirect;
    }

    public function show(Request $request, ExamDocument $examDocument): Response
    {
        Gate::authorize('view', $examDocument);

        $examDocument->load([
            'examPeriod:id,name,academic_year,status',
            'subject:id,name,code',
            'schoolClass:id,name,level',
            'uploader:id,name',
            'approver:id,name',
            'sourceFile',
        ]);

        $source = $examDocument->sourceFile;
        $duplicateExists = false;

        if ($source) {
            $duplicateExists = DocumentFile::query()
                ->where('checksum_sha256', $source->checksum_sha256)
                ->where('file_role', DocumentFileRole::SOURCE)
                ->where('exam_document_id', '!=', $examDocument->id)
                ->whereHas('examDocument', fn ($q) => $q->where('exam_period_id', $examDocument->exam_period_id))
                ->exists();
        }

        return Inertia::render('ExamDocuments/Show', [
            'document' => [
                'id' => $examDocument->id,
                'document_title' => $examDocument->document_title,
                'teacher_name' => $examDocument->teacher_name,
                'subject_id' => $examDocument->subject_id,
                'subject_name' => $examDocument->subject?->name ?? 'Belum ditentukan',
                'school_class_id' => $examDocument->school_class_id,
                'class_name' => $examDocument->schoolClass?->name ?? 'Belum ditentukan',
                'exam_date' => $examDocument->exam_date?->format('Y-m-d'),
                'exam_date_formatted' => $examDocument->exam_date?->format('d M Y'),
                'duration_minutes' => $examDocument->duration_minutes,
                'notes' => $examDocument->notes,
                'source_format' => $examDocument->source_format->value,
                'current_status' => $examDocument->current_status->value,
                'status_label' => $examDocument->current_status->label(),
                'period' => [
                    'id' => $examDocument->examPeriod->id,
                    'name' => $examDocument->examPeriod->name,
                    'academic_year' => $examDocument->examPeriod->academic_year,
                ],
                'uploader_name' => $examDocument->uploader?->name ?? '-',
                'created_at' => $examDocument->created_at?->format('d M Y H:i'),
                'can_edit' => $request->user()->can('update', $examDocument),
                'duplicate_warning' => $duplicateExists,
                'source_file' => $source ? [
                    'id' => $source->id,
                    'original_filename' => $source->original_filename,
                    'mime_type' => $source->mime_type,
                    'human_size' => $source->humanSize(),
                    'checksum_sha256' => $source->checksum_sha256,
                    'download_url' => route('document-files.download', $source->id),
                ] : null,
            ],
            'subjects' => Subject::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'schoolClasses' => SchoolClass::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(UpdateExamDocumentRequest $request, ExamDocument $examDocument): RedirectResponse
    {
        Gate::authorize('update', $examDocument);

        $validated = $request->validated();

        $examDocument->update([
            'subject_id' => $validated['subject_id'] ?? null,
            'school_class_id' => $validated['school_class_id'] ?? null,
            'teacher_name' => trim($validated['teacher_name']),
            'exam_date' => $validated['exam_date'] ?? null,
            'duration_minutes' => $validated['duration_minutes'] ?? null,
            'document_title' => trim($validated['document_title']),
            'notes' => filled($validated['notes'] ?? null) ? trim((string) $validated['notes']) : null,
        ]);

        return redirect()
            ->route('exam-documents.show', $examDocument->id)
            ->with('success', 'Metadata dokumen soal berhasil diperbarui.');
    }
}