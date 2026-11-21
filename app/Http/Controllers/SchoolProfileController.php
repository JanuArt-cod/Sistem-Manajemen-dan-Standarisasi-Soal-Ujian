<?php

namespace App\Http\Controllers;

use App\Http\Requests\SchoolProfile\UpdateSchoolProfileRequest;
use App\Models\SchoolProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolProfileController extends Controller
{
    public function edit(): Response
    {
        Gate::authorize('viewAny', SchoolProfile::class);

        $profile = SchoolProfile::current();

        return Inertia::render('SchoolProfile/Edit', [
            'profile' => $profile ? [
                'id' => $profile->id,
                'institution_name' => $profile->institution_name,
                'foundation_name' => $profile->foundation_name,
                'address' => $profile->address,
                'phone' => $profile->phone,
                'email' => $profile->email,
                'has_logo' => ! empty($profile->logo_path) && Storage::disk('local')->exists($profile->logo_path),
                'logo_url' => ! empty($profile->logo_path) ? route('school-profile.logo', ['v' => $profile->updated_at?->timestamp]) : null,
                'header_lines' => $profile->header_lines ?? [],
            ] : null,
        ]);
    }

    public function update(UpdateSchoolProfileRequest $request): RedirectResponse
    {
        Gate::authorize('update', SchoolProfile::class);

        $validated = $request->validated();
        $profile = SchoolProfile::current() ?? new SchoolProfile(['is_active' => true]);

        $headerLines = collect($validated['header_lines'] ?? [])
            ->map(fn ($line) => trim((string) $line))
            ->filter(fn ($line) => $line !== '')
            ->values()
            ->all();

        $logoPath = $profile->logo_path;

        if ($request->boolean('remove_logo') && $logoPath) {
            Storage::disk('local')->delete($logoPath);
            $logoPath = null;
        }

        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $imageInfo = @getimagesize($file->getRealPath());

            if ($imageInfo === false) {
                return back()->withErrors([
                    'logo' => 'File logo tidak valid sebagai gambar.',
                ]);
            }

            if ($logoPath) {
                Storage::disk('local')->delete($logoPath);
            }

            $extension = strtolower($file->extension() ?: 'png');
            $filename = 'logo_'.Str::uuid().'.'.$extension;
            $logoPath = $file->storeAs('school', $filename, 'local');
        }

        $profile->fill([
            'institution_name' => $validated['institution_name'],
            'foundation_name' => $validated['foundation_name'] ?? null,
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'header_lines' => $headerLines,
            'logo_path' => $logoPath,
            'is_active' => true,
        ]);
        $profile->save();

        return redirect()
            ->route('school-profile.edit')
            ->with('success', 'Profil sekolah dan pengaturan kop berhasil disimpan.');
    }

    public function logo(): StreamedResponse
    {
        $profile = SchoolProfile::current();

        if (! $profile || empty($profile->logo_path) || ! Storage::disk('local')->exists($profile->logo_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($profile->logo_path);
    }
}