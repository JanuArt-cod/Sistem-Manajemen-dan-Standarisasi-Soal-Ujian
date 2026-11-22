<?php

namespace App\Http\Controllers;

use App\Enums\PaperCode;
use App\Enums\PaperOrientation;
use App\Http\Requests\FormattingPresets\StoreFormattingPresetRequest;
use App\Models\FormattingPreset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FormattingPresetController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', FormattingPreset::class);

        $presets = FormattingPreset::query()
            ->with('creator:id,name')
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get()
            ->map(fn (FormattingPreset $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'paper_code' => $p->paper_code->value,
                'paper_label' => $p->paper_code->label(),
                'paper_width_mm' => (float) $p->paper_width_mm,
                'paper_height_mm' => (float) $p->paper_height_mm,
                'orientation' => $p->orientation->value,
                'orientation_label' => $p->orientation->label(),
                'margin_top_mm' => (float) $p->margin_top_mm,
                'margin_bottom_mm' => (float) $p->margin_bottom_mm,
                'margin_left_mm' => (float) $p->margin_left_mm,
                'margin_right_mm' => (float) $p->margin_right_mm,
                'font_family' => $p->font_family,
                'font_size_pt' => (float) $p->font_size_pt,
                'line_spacing' => (float) $p->line_spacing,
                'paragraph_spacing_before_pt' => (float) $p->paragraph_spacing_before_pt,
                'paragraph_spacing_after_pt' => (float) $p->paragraph_spacing_after_pt,
                'preserve_original_styles' => $p->preserve_original_styles,
                'apply_to_existing_tables' => $p->apply_to_existing_tables,
                'is_default' => $p->is_default,
                'is_active' => $p->is_active,
                'creator_name' => $p->creator?->name ?? 'Sistem (Standar Baku)',
            ]);

        return Inertia::render('FormattingPresets/Index', [
            'presets' => $presets,
            'canManage' => $request->user()->can('create', FormattingPreset::class),
            'paperCodes' => collect(PaperCode::cases())->map(fn (PaperCode $code) => [
                'value' => $code->value,
                'label' => $code->label(),
                'dimensions' => $code->defaultDimensions(),
            ])->values(),
            'orientations' => collect(PaperOrientation::cases())->map(fn (PaperOrientation $o) => [
                'value' => $o->value,
                'label' => $o->label(),
            ])->values(),
        ]);
    }

    public function store(StoreFormattingPresetRequest $request): RedirectResponse
    {
        Gate::authorize('create', FormattingPreset::class);

        $validated = $request->validated();
        $isDefault = $request->boolean('is_default');
        $isActive = $isDefault ? true : ($request->has('is_active') ? $request->boolean('is_active') : true);

        DB::transaction(function () use ($validated, $request, $isDefault, $isActive): void {
            $preset = FormattingPreset::query()->create([
                'name' => trim($validated['name']),
                'description' => filled($validated['description'] ?? null) ? trim((string) $validated['description']) : null,
                'paper_code' => $validated['paper_code'],
                'paper_width_mm' => $validated['paper_width_mm'],
                'paper_height_mm' => $validated['paper_height_mm'],
                'orientation' => $validated['orientation'],
                'margin_top_mm' => $validated['margin_top_mm'],
                'margin_bottom_mm' => $validated['margin_bottom_mm'],
                'margin_left_mm' => $validated['margin_left_mm'],
                'margin_right_mm' => $validated['margin_right_mm'],
                'font_family' => trim($validated['font_family']),
                'font_size_pt' => $validated['font_size_pt'],
                'line_spacing' => $validated['line_spacing'],
                'paragraph_spacing_before_pt' => $validated['paragraph_spacing_before_pt'] ?? 0,
                'paragraph_spacing_after_pt' => $validated['paragraph_spacing_after_pt'] ?? 4,
                'preserve_original_styles' => $request->has('preserve_original_styles') ? $request->boolean('preserve_original_styles') : true,
                'apply_to_existing_tables' => $request->boolean('apply_to_existing_tables'),
                'is_default' => false,
                'is_active' => $isActive,
                'created_by' => $request->user()->id,
            ]);

            if ($isDefault || FormattingPreset::query()->where('is_default', true)->doesntExist()) {
                $preset->markAsDefault();
            }
        });

        return redirect()
            ->route('formatting-presets.index')
            ->with('success', 'Preset format dokumen berhasil ditambahkan.');
    }

    public function update(StoreFormattingPresetRequest $request, FormattingPreset $formattingPreset): RedirectResponse
    {
        Gate::authorize('update', $formattingPreset);

        $validated = $request->validated();
        $isDefault = $request->boolean('is_default') || $formattingPreset->is_default;
        $isActive = $isDefault ? true : ($request->has('is_active') ? $request->boolean('is_active') : $formattingPreset->is_active);

        DB::transaction(function () use ($formattingPreset, $validated, $request, $isDefault, $isActive): void {
            $formattingPreset->update([
                'name' => trim($validated['name']),
                'description' => filled($validated['description'] ?? null) ? trim((string) $validated['description']) : null,
                'paper_code' => $validated['paper_code'],
                'paper_width_mm' => $validated['paper_width_mm'],
                'paper_height_mm' => $validated['paper_height_mm'],
                'orientation' => $validated['orientation'],
                'margin_top_mm' => $validated['margin_top_mm'],
                'margin_bottom_mm' => $validated['margin_bottom_mm'],
                'margin_left_mm' => $validated['margin_left_mm'],
                'margin_right_mm' => $validated['margin_right_mm'],
                'font_family' => trim($validated['font_family']),
                'font_size_pt' => $validated['font_size_pt'],
                'line_spacing' => $validated['line_spacing'],
                'paragraph_spacing_before_pt' => $validated['paragraph_spacing_before_pt'] ?? 0,
                'paragraph_spacing_after_pt' => $validated['paragraph_spacing_after_pt'] ?? 4,
                'preserve_original_styles' => $request->has('preserve_original_styles') ? $request->boolean('preserve_original_styles') : true,
                'apply_to_existing_tables' => $request->boolean('apply_to_existing_tables'),
                'is_active' => $isActive,
            ]);

            if ($isDefault) {
                $formattingPreset->markAsDefault();
            }
        });

        return redirect()
            ->route('formatting-presets.index')
            ->with('success', "Preset \"{$formattingPreset->name}\" berhasil diperbarui.");
    }

    public function setDefault(FormattingPreset $formattingPreset): RedirectResponse
    {
        Gate::authorize('update', $formattingPreset);

        $formattingPreset->markAsDefault();

        return redirect()
            ->route('formatting-presets.index')
            ->with('success', "Preset \"{$formattingPreset->name}\" ditetapkan sebagai standar default.");
    }
}