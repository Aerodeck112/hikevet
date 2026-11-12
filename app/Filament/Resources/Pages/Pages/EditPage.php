<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use App\Models\PageVersion;
use App\Support\Audit;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/** Editarea unei pagini pastreaza o versiune a starii anterioare la fiecare salvare. */
class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    private const VERSIONED = ['title', 'slug', 'template', 'status', 'lead', 'body', 'blocks', 'hero_image', 'seo_title', 'seo_description', 'noindex', 'document_version'];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')->label('Previzualizează')->icon('heroicon-o-eye')->url(fn () => site_url($this->record->path()))->openUrlInNewTab(),
            Action::make('restore')->label('Restaurează o versiune')->icon('heroicon-o-arrow-uturn-left')->color('gray')
                ->visible(fn () => $this->record->versions()->exists())
                ->schema([
                    Select::make('version_id')->label('Versiune')->required()
                        ->options(fn () => $this->record->versions()->with('author')->limit(30)->get()
                            ->mapWithKeys(fn (PageVersion $v) => [$v->id => 'v'.$v->version.' · '.$v->created_at->format('d.m.Y H:i').' · '.($v->author?->name ?? 'sistem')])),
                ])
                ->action(function (array $data) {
                    $version = $this->record->versions()->findOrFail($data['version_id']);
                    $this->snapshot();
                    $this->record->forceFill(collect($version->snapshot)->only(self::VERSIONED)->all())->save();
                    Audit::log('page.restored', $this->record, 'Pagina „'.$this->record->title.'” restaurată la v'.$version->version);
                    Notification::make()->title('Versiunea a fost restaurată')->success()->send();
                    $this->redirect(PageResource::getUrl('edit', ['record' => $this->record]));
                }),
            DeleteAction::make()->hidden(fn () => in_array($this->record->slug, ['home', 'abonamente', 'servicii', 'hikevet-contact'], true)),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['block_texts'] = collect($data['blocks'] ?? [])->filter(fn ($v) => is_string($v) || is_null($v))->map(fn ($v) => (string) $v)->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $blocks = $this->record->blocks ?? [];
        foreach ((array) ($data['block_texts'] ?? []) as $key => $value) {
            if (array_key_exists($key, $blocks) && (is_string($blocks[$key]) || is_null($blocks[$key]))) {
                $blocks[$key] = in_array($key, ['rules_html', 'closing', 'how', 'video_vet_intro', 'partners_text'], true) ? \App\Support\Html::clean((string) $value) : strip_tags((string) $value);
            }
        }
        unset($data['block_texts']);
        $data['blocks'] = $blocks ?: null;
        $data['updated_by'] = auth()->id();
        if (($data['status'] ?? null) === 'published' && ! $this->record->published_at) {
            $data['published_at'] = now();
        }

        return $data;
    }

    protected function beforeSave(): void
    {
        $this->snapshot();
    }

    protected function afterSave(): void
    {
        Audit::log('page.updated', $this->record, 'Pagina „'.$this->record->title.'” a fost modificată');
    }

    private function snapshot(): void
    {
        /** @var Page $original */
        $original = Page::query()->find($this->record->id);
        if (! $original) {
            return;
        }

        PageVersion::create([
            'page_id' => $original->id,
            'version' => (int) $original->versions()->max('version') + 1,
            'snapshot' => $original->only(self::VERSIONED),
            'created_by' => auth()->id(),
        ]);
    }
}
