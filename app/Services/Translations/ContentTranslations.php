<?php

namespace App\Services\Translations;

use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\TeamMember;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/** Citirea (cu cache pe cerere) si importul traducerilor de continut. */
class ContentTranslations
{
    /** @var array<string, array<int, array<string, string>>> */
    private static array $cache = [];

    public static function flush(): void
    {
        self::$cache = [];
    }

    public static function lookup(Model $model, string $locale, string $field): ?string
    {
        $type = $model->getMorphClass();
        $key = $type.'|'.$locale;
        if (! isset(self::$cache[$key])) {
            self::$cache[$key] = [];
            try {
                foreach (Translation::query()->where('translatable_type', $type)->where('locale', $locale)->get(['translatable_id', 'field', 'value']) as $t) {
                    self::$cache[$key][$t->translatable_id][$t->field] = (string) $t->value;
                }
            } catch (\Throwable) {
                // Tabelul poate lipsi inainte de migrare: afisam romana.
            }
        }

        return self::$cache[$key][$model->getKey()][$field] ?? null;
    }

    /**
     * Blocurile paginilor: textele vin din traducere, iar imaginile, adresele si numerele raman cele din romana
     * (ca o imagine schimbata in admin sa apara in toate limbile).
     */
    public static function mergeBlocks(array $base, array $translated): array
    {
        foreach ($base as $k => $v) {
            if (! array_key_exists($k, $translated)) {
                continue;
            }
            if (is_array($v) && is_array($translated[$k])) {
                $base[$k] = self::mergeBlocks($v, $translated[$k]);
            } elseif (is_string($v) && is_string($translated[$k]) && ! self::isTechnical($v)) {
                $base[$k] = $translated[$k];
            }
        }

        return $base;
    }

    private static function isTechnical(string $v): bool
    {
        return $v === '' || is_numeric($v) || str_starts_with($v, 'http') || (! str_contains($v, ' ') && str_contains($v, '/'));
    }

    /** Ce modele si campuri se traduc, plus cum se gasesc in fisierele livrate (dupa slug sau dupa intrebare). */
    public const MAP = [
        'services' => [Service::class, 'slug'],
        'posts' => [Post::class, 'slug'],
        'pages' => [Page::class, 'slug'],
        'team' => [TeamMember::class, 'slug'],
        'service_categories' => [ServiceCategory::class, 'slug'],
        'faqs' => [Faq::class, 'question'],
    ];

    /**
     * Importa traducerile din database/seeders/data/i18n/{en,hu}-*.json. Nu suprascrie traducerile deja
     * existente (cele editate in admin raman). Returneaza numarul de campuri adaugate.
     */
    public function importBundled(bool $overwrite = false): int
    {
        if (! Schema::hasTable('translations')) {
            return 0;
        }
        $added = 0;
        foreach (glob(database_path('seeders/data/i18n/{en,hu}-*.json'), GLOB_BRACE) ?: [] as $file) {
            $locale = substr(basename($file), 0, 2);
            $data = json_decode((string) file_get_contents($file), true) ?: [];
            foreach ($data as $group => $items) {
                if (! isset(self::MAP[$group])) {
                    continue;
                }
                [$class, $by] = self::MAP[$group];
                $models = $class::query()->get();
                foreach ($items as $ref => $fields) {
                    $model = $by === 'question'
                        ? $models->first(fn ($m) => substr(md5(trim((string) $m->getRawOriginal('question'))), 0, 12) === $ref)
                        : $models->firstWhere('slug', $ref);
                    if (! $model) {
                        continue;
                    }
                    foreach ($fields as $field => $value) {
                        $value = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $value;
                        $where = ['translatable_type' => $model->getMorphClass(), 'translatable_id' => $model->getKey(), 'locale' => $locale, 'field' => $field];
                        $exists = Translation::query()->where($where)->exists();
                        if ($exists && ! $overwrite) {
                            continue;
                        }
                        Translation::query()->updateOrCreate($where, ['value' => $value]);
                        $added++;
                    }
                }
            }
        }
        self::flush();

        return $added;
    }
}
