<?php

namespace App\Filament\Support;

use App\Models\Media;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Str;

/** Campuri reutilizate in formularele din admin. */
class Fields
{
    public static function slug(string $from = 'name'): TextInput
    {
        return TextInput::make('slug')->label('Adresă (slug)')->required()->maxLength(190)
            ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->unique(ignoreRecord: true)
            ->helperText('Doar litere mici, cifre și cratime. Schimbarea adresei unei pagini publicate rupe linkurile existente: adaugă și un redirect 301.');
    }

    public static function title(string $name = 'name', string $label = 'Nume'): TextInput
    {
        return TextInput::make($name)->label($label)->required()->maxLength(190)->live(onBlur: true)
            ->afterStateUpdated(function ($state, callable $set, callable $get, string $operation) {
                if ($operation === 'create' && blank($get('slug'))) {
                    $set('slug', Str::slug((string) $state));
                }
            });
    }

    /** Editor de text formatat. Continutul este curatat la salvare (vezi App\Support\Html). */
    public static function rich(string $name, string $label): RichEditor
    {
        return RichEditor::make($name)->label($label)->columnSpanFull()
            ->toolbarButtons([['bold', 'italic', 'link'], ['h2', 'h3'], ['bulletList', 'orderedList', 'blockquote'], ['undo', 'redo']])
            ->dehydrateStateUsing(fn ($state) => \App\Support\Html::clean((string) $state));
    }

    /** Alege o imagine din biblioteca media (public/media si public/uploads). */
    public static function image(string $name, string $label): Select
    {
        return Select::make($name)->label($label)->searchable()->preload(false)
            ->getSearchResultsUsing(fn (string $search) => Media::query()->where('mime', 'like', 'image/%')
                ->where(fn ($q) => $q->where('original_name', 'like', "%{$search}%")->orWhere('alt', 'like', "%{$search}%")->orWhere('path', 'like', "%{$search}%"))
                ->limit(30)->get()->mapWithKeys(fn (Media $m) => [self::mediaValue($m) => ($m->alt ?: $m->original_name).' ('.$m->width.'×'.$m->height.')'])->all())
            ->getOptionLabelUsing(fn ($value) => $value ? basename((string) $value) : null)
            ->helperText('Scrie o parte din numele fișierului sau din textul alternativ. Imaginile noi se încarcă în Bibliotecă media.');
    }

    public static function mediaValue(Media $media): string
    {
        return $media->disk === 'public' ? 'uploads/'.$media->path : $media->path;
    }

    public static function seo(): Section
    {
        return Section::make('SEO')->collapsed()->columns(1)->schema([
            TextInput::make('seo_title')->label('Titlu pentru Google')->maxLength(70)->helperText('Maximum 60–70 de caractere. Gol = se folosește titlul.'),
            Textarea::make('seo_description')->label('Descriere pentru Google')->rows(2)->maxLength(320)->helperText('Recomandat 120–160 de caractere.'),
        ]);
    }
}
