<?php

namespace App\Filament\Resources\ScheduleBlocks;

use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use App\Models\ScheduleBlock;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ScheduleBlockResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = ScheduleBlock::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    protected static string|UnitEnum|null $navigationGroup = 'Clinică';

    protected static ?string $navigationLabel = 'Zile libere și pauze';

    protected static ?string $modelLabel = 'interval blocat';

    protected static ?string $pluralModelLabel = 'intervale blocate';

    protected static ?int $navigationSort = 25;

    public static function permission(): string
    {
        return Permission::APPOINTMENTS_MANAGE;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('team_member_id')->label('Medic')->relationship('doctor', 'name')->placeholder('Toată clinica')->helperText('Gol = intervalul este blocat pentru toți medicii.'),
            TextInput::make('reason')->label('Motiv')->maxLength(190)->placeholder('Concediu, pauză, sărbătoare legală'),
            DateTimePicker::make('starts_at')->label('De la')->seconds(false)->minutesStep(15)->required(),
            DateTimePicker::make('ends_at')->label('Până la')->seconds(false)->minutesStep(15)->required()->after('starts_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('starts_at', 'desc')->columns([
            TextColumn::make('doctor.name')->label('Medic')->placeholder('Toată clinica'),
            TextColumn::make('starts_at')->label('De la')->dateTime('d.m.Y H:i')->sortable(),
            TextColumn::make('ends_at')->label('Până la')->dateTime('d.m.Y H:i'),
            TextColumn::make('reason')->label('Motiv'),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListScheduleBlocks::route('/'), 'create' => Pages\CreateScheduleBlock::route('/create'), 'edit' => Pages\EditScheduleBlock::route('/{record}/edit')];
    }
}
