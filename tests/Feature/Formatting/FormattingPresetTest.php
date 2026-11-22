<?php

namespace Tests\Feature\Formatting;

use App\Enums\PaperCode;
use App\Enums\PaperOrientation;
use App\Models\FormattingPreset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormattingPresetTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_f4_preset_exists_after_migration(): void
    {
        $preset = FormattingPreset::defaultPreset();

        $this->assertNotNull($preset);
        $this->assertEquals(PaperCode::F4, $preset->paper_code);
        $this->assertEquals(215.0, $preset->paper_width_mm);
        $this->assertEquals(330.0, $preset->paper_height_mm);
        $this->assertEquals(PaperOrientation::PORTRAIT, $preset->orientation);
        $this->assertEquals(20.0, $preset->margin_top_mm);
        $this->assertEquals(20.0, $preset->margin_bottom_mm);
        $this->assertEquals(25.0, $preset->margin_left_mm);
        $this->assertEquals(20.0, $preset->margin_right_mm);
        $this->assertEquals('Times New Roman', $preset->font_family);
        $this->assertEquals(12.0, $preset->font_size_pt);
        $this->assertEquals(1.0, $preset->line_spacing);
        $this->assertTrue($preset->is_default);
    }

    public function test_admin_can_create_update_and_switch_default_preset(): void
    {
        $admin = User::factory()->admin()->create();
        $initialDefault = FormattingPreset::defaultPreset();

        $this->actingAs($admin)->post('/formatting-presets', [
            'name' => 'Preset A4 Cadangan',
            'description' => 'Untuk naskah cetak ukuran A4',
            'paper_code' => PaperCode::A4->value,
            'paper_width_mm' => 210,
            'paper_height_mm' => 297,
            'orientation' => PaperOrientation::PORTRAIT->value,
            'margin_top_mm' => 20,
            'margin_bottom_mm' => 20,
            'margin_left_mm' => 25,
            'margin_right_mm' => 20,
            'font_family' => 'Times New Roman',
            'font_size_pt' => 12,
            'line_spacing' => 1.15,
            'is_default' => false,
            'is_active' => true,
        ])->assertRedirect('/formatting-presets');

        $created = FormattingPreset::query()->where('name', 'Preset A4 Cadangan')->firstOrFail();
        $this->assertFalse($created->is_default);

        $this->actingAs($admin)
            ->patch("/formatting-presets/{$created->id}/default")
            ->assertRedirect('/formatting-presets');

        $created->refresh();
        $initialDefault->refresh();

        $this->assertTrue($created->is_default);
        $this->assertFalse($initialDefault->is_default);
    }

    public function test_operator_can_view_presets_but_cannot_mutate_them(): void
    {
        $operator = User::factory()->operator()->create();
        $preset = FormattingPreset::defaultPreset();

        $this->actingAs($operator)->get('/formatting-presets')->assertStatus(200);

        $this->actingAs($operator)->post('/formatting-presets', [
            'name' => 'Preset Tidak Sah',
            'paper_code' => PaperCode::A4->value,
            'paper_width_mm' => 210,
            'paper_height_mm' => 297,
            'orientation' => PaperOrientation::PORTRAIT->value,
            'margin_top_mm' => 20,
            'margin_bottom_mm' => 20,
            'margin_left_mm' => 20,
            'margin_right_mm' => 20,
            'font_family' => 'Times New Roman',
            'font_size_pt' => 12,
            'line_spacing' => 1.0,
        ])->assertStatus(403);

        $this->actingAs($operator)
            ->patch("/formatting-presets/{$preset->id}/default")
            ->assertStatus(403);
    }

    public function test_active_default_preset_cannot_be_deactivated(): void
    {
        $admin = User::factory()->admin()->create();
        $preset = FormattingPreset::defaultPreset();

        $this->actingAs($admin)
            ->from('/formatting-presets')
            ->put("/formatting-presets/{$preset->id}", [
                'name' => $preset->name,
                'paper_code' => $preset->paper_code->value,
                'paper_width_mm' => $preset->paper_width_mm,
                'paper_height_mm' => $preset->paper_height_mm,
                'orientation' => $preset->orientation->value,
                'margin_top_mm' => $preset->margin_top_mm,
                'margin_bottom_mm' => $preset->margin_bottom_mm,
                'margin_left_mm' => $preset->margin_left_mm,
                'margin_right_mm' => $preset->margin_right_mm,
                'font_family' => $preset->font_family,
                'font_size_pt' => $preset->font_size_pt,
                'line_spacing' => $preset->line_spacing,
                'is_active' => false,
            ])
            ->assertSessionHasErrors('is_active');
    }
}