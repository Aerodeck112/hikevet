<?php

namespace App\Filament\Resources\MediaItems\Pages;

use App\Filament\Resources\MediaItems\MediaResource;
use App\Services\Media\MediaLibrary;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMedia extends CreateRecord
{
    protected static string $resource = MediaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $media = app(MediaLibrary::class)->register($data['upload'], $data['original_name'] ?? null, $data['alt'] ?? null, $data['category'] ?? 'site');
        $media->update(['license_note' => $data['license_note'] ?? null, 'focal_x' => $data['focal_x'] ?? 50, 'focal_y' => $data['focal_y'] ?? 50]);

        return $media;
    }

    protected function getRedirectUrl(): string
    {
        return MediaResource::getUrl('index');
    }
}
