<?php

namespace App\Services\Campaign;

use App\Models\SterilizationCase;
use App\Models\SterilizationDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Fotografiile pacientilor si actele de identitate: pe discul privat, cu amprenta SHA-256.
 * Un fisier inlocuit sau scos nu se sterge (pluginul il stergea); ramane in istoricul dosarului.
 */
class CampaignFiles
{
    public const MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];

    public const MAX_BYTES = 20 * 1024 * 1024;

    /** @return string referinta salvata in dosar (doc:ID) */
    public function store(SterilizationCase $case, UploadedFile $file, string $kind, string $source, ?User $actor): string
    {
        $mime = (string) $file->getMimeType();
        if (! isset(self::MIME[$mime]) || ($kind === SterilizationDocument::PHOTO && $mime === 'application/pdf')) {
            throw ValidationException::withMessages(['file' => $kind === SterilizationDocument::PHOTO
                ? 'Fotografia trebuie să fie JPG, PNG sau WebP.' : 'Actul de identitate trebuie să fie JPG, PNG, WebP sau PDF.']);
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'Fișierul depășește 20 MB.']);
        }
        if (str_starts_with($mime, 'image/') && ! @getimagesize($file->getRealPath())) {
            throw ValidationException::withMessages(['file' => 'Imaginea nu poate fi citită.']);
        }

        $sha = hash_file('sha256', $file->getRealPath());
        $path = 'sterilization/'.$case->id.'/'.$sha.'.'.self::MIME[$mime];
        if (! Storage::disk('private')->exists($path)) {
            Storage::disk('private')->putFileAs(dirname($path), $file, basename($path));
        }

        $this->supersede($case, $kind);
        $doc = SterilizationDocument::query()->create([
            'sterilization_case_id' => $case->id, 'kind' => $kind, 'source' => $source, 'stored_path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 190), 'sha256' => $sha, 'size' => $file->getSize(),
            'mime' => $mime, 'is_missing' => false, 'uploaded_by' => $actor?->id,
        ]);

        return 'doc:'.$doc->id;
    }

    /** Marcheaza documentul curent ca inlocuit/scos, fara a-l sterge. */
    public function supersede(SterilizationCase $case, string $kind): void
    {
        SterilizationDocument::query()->where('sterilization_case_id', $case->id)->where('kind', $kind)
            ->whereNull('superseded_at')->update(['superseded_at' => now()]);
    }

    /** Pentru vizitele cu mai multi pacienti: acelasi CI (acelasi fisier) se leaga si de celelalte dosare. */
    public function shareIdCard(SterilizationCase $from, SterilizationCase $to): ?string
    {
        $doc = SterilizationDocument::query()->where('sterilization_case_id', $from->id)->where('kind', SterilizationDocument::ID_CARD)
            ->whereNull('superseded_at')->latest('id')->first();
        if (! $doc) {
            $this->supersede($to, SterilizationDocument::ID_CARD);

            return null;
        }
        $current = SterilizationDocument::query()->where('sterilization_case_id', $to->id)->where('kind', SterilizationDocument::ID_CARD)
            ->whereNull('superseded_at')->latest('id')->first();
        if ($current && $current->sha256 === $doc->sha256) {
            return 'doc:'.$current->id;
        }
        $this->supersede($to, SterilizationDocument::ID_CARD);
        $copy = $doc->replicate(['superseded_at']);
        $copy->sterilization_case_id = $to->id;
        $copy->source = 'Sincronizare vizită';
        $copy->save();

        return 'doc:'.$copy->id;
    }

    /** Documentul la care trimite o referinta din dosar (doc:ID sau URL-ul vechi din WordPress). */
    public function resolve(SterilizationCase $case, string $kind): ?SterilizationDocument
    {
        return SterilizationDocument::query()->where('sterilization_case_id', $case->id)->where('kind', $kind)
            ->whereNull('superseded_at')->latest('id')->first();
    }
}
