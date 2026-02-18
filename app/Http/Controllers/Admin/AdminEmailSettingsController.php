<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * AdminEmailSettingsController
 *
 * Gerencia configurações visuais dos e-mails, como logo.
 * O logo é armazenado em storage/app/public/email-assets/logo.*
 * e sua URL pública é injetada como variável {{logo_url}} em todos os templates.
 */
class AdminEmailSettingsController extends Controller
{
    /**
     * Retorna a URL do logo atual.
     */
    public function getLogo()
    {
        $setting = DB::table('system_settings')->where('key', 'email_logo_url')->first();

        return response()->json([
            'logo_url' => $setting->value ?? null,
        ]);
    }

    /**
     * Upload de logo para e-mails.
     * Aceita PNG, JPG, SVG, WEBP. Máximo 2MB.
     */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|file|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        $file = $request->file('logo');
        $filename = 'logo_' . time() . '.' . $file->getClientOriginalExtension();

        // Remove logo anterior
        $oldFiles = Storage::disk('public')->files('email-assets');
        foreach ($oldFiles as $oldFile) {
            if (str_starts_with(basename($oldFile), 'logo_')) {
                Storage::disk('public')->delete($oldFile);
            }
        }

        // Salva novo logo
        $path = $file->storeAs('email-assets', $filename, 'public');
        $url = Storage::disk('public')->url($path);

        // Salva URL na tabela de configurações
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'email_logo_url'],
            ['value' => $url, 'updated_at' => now()]
        );

        return response()->json(['logo_url' => $url]);
    }

    /**
     * Remove o logo.
     */
    public function deleteLogo()
    {
        $oldFiles = Storage::disk('public')->files('email-assets');
        foreach ($oldFiles as $oldFile) {
            if (str_starts_with(basename($oldFile), 'logo_')) {
                Storage::disk('public')->delete($oldFile);
            }
        }

        DB::table('system_settings')->where('key', 'email_logo_url')->delete();

        return response()->json(['message' => 'Logo removido com sucesso.']);
    }
}
