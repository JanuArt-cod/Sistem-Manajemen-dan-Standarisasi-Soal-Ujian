<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Models\ExamDocument;
use App\Models\FormattingPreset;
use App\Services\FormattingSnapshotBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DocumentProcessingConfigController extends Controller
{
    public function update(
        Request $request,
        ExamDocument $examDocument,
        FormattingSnapshotBuilder $snapshotBuilder
    ): RedirectResponse {
        Gate::authorize('update', $examDocument);

        $validated = $request->validate([
            'formatting_preset_id' => [
                'required',
                'integer',
                Rule::exists('formatting_presets', 'id')->where(fn ($q) => $q->where('is_active', true)->whereNull('deleted_at')),
            ],
            'document_template_id' => [
                'required',
                'integer',
                Rule::exists('document_templates', 'id')->where(fn ($q) => $q->where('is_active', true)->whereNull('deleted_at')),
            ],
        ], [
            'formatting_preset_id.required' => 'Preset format halaman wajib dipilih.',
            'formatting_preset_id.exists' => 'Preset format yang dipilih tidak aktif atau tidak ditemukan.',
            'document_template_id.required' => 'Template kop halaman pertama wajib dipilih.',
            'document_template_id.exists' => 'Template kop yang dipilih tidak aktif atau tidak ditemukan.',
        ]);

        $preset = FormattingPreset::query()->findOrFail($validated['formatting_preset_id']);
        $template = DocumentTemplate::query()->findOrFail($validated['document_template_id']);

        $snapshotBuilder->buildAndSave($examDocument, $preset, $template, $request->user());

        return redirect()
            ->route('exam-documents.show', $examDocument)
            ->with('success', 'Konfigurasi format F4 & template kop berhasil dikunci sebagai snapshot dokumen.');
    }
}