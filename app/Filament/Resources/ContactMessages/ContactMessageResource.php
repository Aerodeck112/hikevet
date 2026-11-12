<?php

namespace App\Filament\Resources\ContactMessages;

use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ContactMessageResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|UnitEnum|null $navigationGroup = 'Clinică';

    protected static ?string $navigationLabel = 'Mesaje din site';

    protected static ?string $modelLabel = 'mesaj';

    protected static ?string $pluralModelLabel = 'mesaje';

    protected static ?int $navigationSort = 60;

    public static function permission(): string
    {
        return Permission::MESSAGES_MANAGE;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $new = ContactMessage::query()->where('status', 'new')->count();

        return $new ? (string) $new : null;
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('created_at')->label('Primit')->dateTime('d.m.Y H:i')->sortable(),
            TextColumn::make('name')->label('Nume')->searchable()->description(fn (ContactMessage $record) => $record->email.($record->phone ? ' · '.$record->phone : '')),
            TextColumn::make('message')->label('Mesaj')->wrap()->limit(220),
            TextColumn::make('status')->label('Stare')->badge()->formatStateUsing(fn ($state) => $state === 'new' ? 'Nou' : 'Rezolvat')->color(fn ($state) => $state === 'new' ? 'warning' : 'gray'),
        ])->filters([SelectFilter::make('status')->label('Stare')->options(['new' => 'Noi', 'handled' => 'Rezolvate'])])
            ->recordActions([
                Action::make('reply')->label('Răspunde')->icon('heroicon-o-arrow-uturn-left')->url(fn (ContactMessage $record) => 'mailto:'.$record->email.'?subject='.rawurlencode('Re: mesajul tău către HiKeVet')),
                Action::make('handled')->label('Marchează rezolvat')->icon('heroicon-o-check')->color('success')->visible(fn (ContactMessage $record) => $record->status === 'new')
                    ->action(fn (ContactMessage $record) => $record->update(['status' => 'handled', 'handled_by' => auth()->id(), 'handled_at' => now()])),
            ])->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListContactMessages::route('/')];
    }
}
