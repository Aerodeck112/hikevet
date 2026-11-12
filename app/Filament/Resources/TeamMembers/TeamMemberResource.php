<?php

namespace App\Filament\Resources\TeamMembers;

use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use App\Filament\Support\Fields;
use App\Models\TeamMember;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class TeamMemberResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = TeamMember::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Conținut site';

    protected static ?string $navigationLabel = 'Echipă și orar';

    protected static ?string $modelLabel = 'membru al echipei';

    protected static ?string $pluralModelLabel = 'echipă';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 30;

    public static function permission(): string
    {
        return Permission::CMS_MANAGE;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profil public')->columns(2)->schema([
                Fields::title(),
                Fields::slug(),
                TextInput::make('role_title')->label('Funcție')->maxLength(120),
                TextInput::make('specializations')->label('Specializări')->maxLength(250),
                Fields::rich('bio', 'Biografie'),
                Fields::image('photo', 'Fotografie'),
                Select::make('user_id')->label('Cont de utilizator')->relationship('user', 'name')->searchable()->helperText('Opțional: leagă profilul de contul medicului.'),
                Select::make('services')->label('Servicii')->relationship('services', 'name')->multiple()->preload(),
                Toggle::make('is_published')->label('Afișat pe site')->default(true),
                TextInput::make('sort')->label('Ordine')->numeric()->default(0),
            ]),
            Section::make('Orar pentru programări online')
                ->description('Intervalele în care medicul poate primi programări. Fără orar, medicul nu apare la programarea online.')
                ->schema([
                    Repeater::make('schedules')->label('')->relationship()->columns(3)->addActionLabel('Adaugă interval')->defaultItems(0)->schema([
                        Select::make('weekday')->label('Zi')->required()->options([1 => 'Luni', 2 => 'Marți', 3 => 'Miercuri', 4 => 'Joi', 5 => 'Vineri', 6 => 'Sâmbătă', 7 => 'Duminică']),
                        TimePicker::make('starts_at')->label('De la')->seconds(false)->minutesStep(15)->required(),
                        TimePicker::make('ends_at')->label('Până la')->seconds(false)->minutesStep(15)->required()->after('starts_at'),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->reorderable('sort')->columns([
            TextColumn::make('name')->label('Nume')->searchable(),
            TextColumn::make('role_title')->label('Funcție'),
            TextColumn::make('schedules_count')->label('Intervale orar')->counts('schedules'),
            IconColumn::make('is_published')->label('Pe site')->boolean(),
        ])->recordActions([EditAction::make()])->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListTeamMembers::route('/'), 'create' => Pages\CreateTeamMember::route('/create'), 'edit' => Pages\EditTeamMember::route('/{record}/edit')];
    }
}
