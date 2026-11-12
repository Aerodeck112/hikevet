<?php

namespace App\Filament\Resources\ScheduleBlocks\Pages;

use App\Filament\Resources\ScheduleBlocks\ScheduleBlockResource;
use Filament\Resources\Pages\EditRecord;

class EditScheduleBlock extends EditRecord
{
    protected static string $resource = ScheduleBlockResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
