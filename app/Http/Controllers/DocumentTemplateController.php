<?php

namespace App\Http\Controllers;

use App\Enums\TemplateHeaderMode;
use App\Http\Requests\DocumentTemplates\StoreDocumentTemplateRequest;
use App\Models\DocumentTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', DocumentTemplate::class);

        $templates = DocumentTemplate::query()
            ->with('creator:id,name')
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get()
            ->map(fn (DocumentTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'header_mode' => $t->header_mode->value,
                'header_mode_label' => $t->header_mode->label(),
                'first_page_only' => $t->first_page_only,
                'show_school_logo' => $t->show_school_logo,
                'show_exam_identity_table' => $t->show_exam_identity_table,
                'header_title' => $t->header_title,
                'header_subtitle' => $t->header_subtitle,
                'instruction_text' => $t->instruction_text,
                'has_custom_docx' => $t->hasCustomDocxFile(),
                'template_original_name' => $t->template_original_name,
                'template_checksum' => $t->template_checksum,
                'is_default' => $t->is_default,
                'is_active' => $t->is_active,
                'creator_name' => $t->creator?->name ?? 'Sistem (Standar Baku)',
            ]);

        return Inertia::render('DocumentTemplates/Index', [
            'templates' => $templates,
            'canManage' => $request->user()->can('create', DocumentTemplate::class),
            'headerModes' => collect(TemplateHeaderMode::cases())->map(fn (TemplateHeaderMode $m) => [
                'value' => $m->value,
                'label' => $m->label(),
            ])->values(),
        ]);
    }

    public function store(StoreDocumentTemplateRequest $request): RedirectResponse
    {
        Gate::authorize('create', DocumentTemplate::class);

        $validated = $request->validated();
        $mode = TemplateHeaderMode::from($validated['header_mode']);
        $isDefault = $request->boolean('is_default');
        $isActive = $isDefault ? true : ($request->has('is_active') ? $request->boolean('is_active') : true);

        $fileMeta = $this->storeUploadedTemplateIfPresent($request, $mode);

        DB::transaction(function () use ($validated, $request, $mode, $isDefault, $isActive, $fileMeta): void {
            $template = DocumentTemplate::query()->create([
                'name' => trim($validated['name']),
                'description' => filled($validated['description'] ?? null) ? trim((string) $validated['description']) : null,
                'header_mode' => $mode,
                'first_page_only' => true, // Terkunci sesuai D-016
                'show_school_logo' => $mode === TemplateHeaderMode::STANDARD_SCHOOL
                    ? ($request->has('show_school_logo') ? $request->boolean('show_school_logo') : true)
                    : false,
                'show_exam_identity_table' => $mode === TemplateHeaderMode::STANDARD_SCHOOL
                    ? ($request->has('show_exam_identity_table') ? $request->boolean('show_exam_identity_table') : true)
                    : false,
                'header_title' => filled($validated['header_title'] ?? null) ? trim((string) $validated['header_title']) : null,
                'header_subtitle' => filled($validated['header_subtitle'] ?? null) ? trim((string) $validated['header_subtitle']) : null,
                'instruction_text' => filled($validated['instruction_text'] ?? null) ? trim((string) $validated['instruction_text']) : null,
                'template_file_disk' => $fileMeta['disk'],
                'template_file_path' => $fileMeta['path'],
                'template_original_name' => $fileMeta['original_name'],
                'template_checksum' => $fileMeta['checksum'],
                'is_default' => false,
                'is_active' => $isActive,
                'created_by' => $request->user()->id,
            ]);

            if ($isDefault || DocumentTemplate::query()->where('is_default', true)->doesntExist()) {
                $template->markAsDefault();
            }
        });

        return redirect()
            ->route('document-templates.index')
            ->with('success', 'Template kop halaman pertama berhasil ditambahkan.');
    }

    public function update(StoreDocumentTemplateRequest $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        Gate::authorize('update', $documentTemplate);

        $validated = $request->validated();
        $mode = TemplateHeaderMode::from($validated['header_mode']);
        $isDefault = $request->boolean('is_default') || $documentTemplate->is_default;
        $isActive = $isDefault ? true : ($request->has('is_active') ? $request->boolean('is_active') : $documentTemplate->is_active);

        $oldDisk = $documentTemplate->template_file_disk;
        $oldPath = $documentTemplate->template_file_path;

        $fileMeta = [
            'disk' => $mode === TemplateHeaderMode::CUSTOM_DOCX ? $documentTemplate->template_file_disk : null,
            'path' => $mode === TemplateHeaderMode::CUSTOM_DOCX ? $documentTemplate->template_file_path : null,
            'original_name' => $mode === TemplateHeaderMode::CUSTOM_DOCX ? $documentTemplate->template_original_name : null,
            'checksum' => $mode === TemplateHeaderMode::CUSTOM_DOCX ? $documentTemplate->template_checksum : null,
        ];

        $uploadedNewFile = false;
        if ($mode === TemplateHeaderMode::CUSTOM_DOCX && $request->hasFile('template_file')) {
            $fileMeta = $this->storeUploadedTemplateIfPresent($request, $mode);
            $uploadedNewFile = true;
        }

        DB::transaction(function () use ($documentTemplate, $validated, $request, $mode, $isDefault, $isActive, $fileMeta): void {
            $documentTemplate->update([
                'name' => trim($validated['name']),
                'description' => filled($validated['description'] ?? null) ? trim((string) $validated['description']) : null,
                'header_mode' => $mode,
                'first_page_only' => true, // Terkunci sesuai D-016
                'show_school_logo' => $mode === TemplateHeaderMode::STANDARD_SCHOOL
                    ? ($request->has('show_school_logo') ? $request->boolean('show_school_logo') : true)
                    : false,
                'show_exam_identity_table' => $mode === TemplateHeaderMode::STANDARD_SCHOOL
                    ? ($request->has('show_exam_identity_table') ? $request->boolean('show_exam_identity_table') : true)
                    : false,
                'header_title' => filled($validated['header_title'] ?? null) ? trim((string) $validated['header_title']) : null,
                'header_subtitle' => filled($validated['header_subtitle'] ?? null) ? trim((string) $validated['header_subtitle']) : null,
                'instruction_text' => filled($validated['instruction_text'] ?? null) ? trim((string) $validated['instruction_text']) : null,
                'template_file_disk' => $fileMeta['disk'],
                'template_file_path' => $fileMeta['path'],
                'template_original_name' => $fileMeta['original_name'],
                'template_checksum' => $fileMeta['checksum'],
                'is_active' => $isActive,
            ]);

            if ($isDefault) {
                $documentTemplate->markAsDefault();
            }
        });

        if (($uploadedNewFile || $mode !== TemplateHeaderMode::CUSTOM_DOCX) && filled($oldDisk) && filled($oldPath)) {
            Storage::disk($oldDisk)->delete($oldPath);
        }

        return redirect()
            ->route('document-templates.index')
            ->with('success', "Template kop \"{$documentTemplate->name}\" berhasil diperbarui.");
    }

    public function setDefault(DocumentTemplate $documentTemplate): RedirectResponse
    {
        Gate::authorize('update', $documentTemplate);

        $documentTemplate->markAsDefault();

        return redirect()
            ->route('document-templates.index')
            ->with('success', "Template \"{$documentTemplate->name}\" ditetapkan sebagai standar default.");
    }

    public function download(DocumentTemplate $documentTemplate): StreamedResponse
    {
        Gate::authorize('download', $documentTemplate);

        $disk = (string) $documentTemplate->template_file_disk;
        $path = (string) $documentTemplate->template_file_path;

        abort_unless(Storage::disk($disk)->exists($path), 404, 'File template kop tidak ditemukan.');

        $downloadName = $documentTemplate->template_original_name ?: 'template-kop.docx';

        return Storage::disk($disk)->download($path, $downloadName);
    }

    /**
     * @return array{disk: ?string, path: ?string, original_name: ?string, checksum: ?string}
     */
    private function storeUploadedTemplateIfPresent(
        StoreDocumentTemplateRequest $request,
        TemplateHeaderMode $mode
    ): array {
        if ($mode !== TemplateHeaderMode::CUSTOM_DOCX || ! $request->hasFile('template_file')) {
            return [
                'disk' => null,
                'path' => null,
                'original_name' => null,
                'checksum' => null,
            ];
        }

        $file = $request->file('template_file');
        $disk = 'local';
        $safeFilename = now()->format('YmdHis') . '_' . Str::random(24) . '.docx';
        $storedPath = $file->storeAs('templates/kop', $safeFilename, $disk);

        return [
            'disk' => $disk,
            'path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'checksum' => $request->validatedDocxChecksum,
        ];
    }
}