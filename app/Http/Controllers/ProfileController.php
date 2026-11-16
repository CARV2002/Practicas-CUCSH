<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        $correoCambio = $user->isDirty('email');

        if ($correoCambio) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($correoCambio) {
            $user->sendEmailVerificationNotification();
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function actualizarFoto(Request $request): RedirectResponse
    {
        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=100,min_height=100'],
        ], [
            'foto.required' => 'Selecciona una imagen.',
            'foto.image' => 'El archivo debe ser una imagen.',
            'foto.mimes' => 'La foto debe ser JPG, PNG o WebP.',
            'foto.max' => 'La foto no debe pesar más de 2 MB.',
            'foto.dimensions' => 'La foto debe medir al menos 100 × 100 píxeles.',
        ]);

        $user = $request->user();
        $user->addMediaFromRequest('foto')->toMediaCollection('foto');
        $user->forceFill(['foto_actualizada_at' => now()])->saveQuietly();

        return back()->with('status', 'foto-updated');
    }

    public function eliminarFoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->clearMediaCollection('foto');
        $user->forceFill(['foto_actualizada_at' => null])->saveQuietly();

        return back()->with('status', 'foto-deleted');
    }

}
