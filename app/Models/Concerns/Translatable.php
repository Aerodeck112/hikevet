<?php

namespace App\Models\Concerns;

use App\Services\Translations\ContentTranslations;
use App\Support\Locales;

/**
 * Campurile de continut se afiseaza in limba vizitatorului daca exista traducere; altfel raman in romana.
 * In romana (inclusiv in admin) modelul se comporta exact ca inainte.
 *
 * @property-read list<string> $translatable
 */
trait Translatable
{
    public function getAttributeValue($key)
    {
        $value = parent::getAttributeValue($key);
        $locale = app()->getLocale();
        if ($locale === Locales::DEFAULT || ! $this->exists || ! in_array($key, $this->translatable, true)) {
            return $value;
        }

        $translated = ContentTranslations::lookup($this, $locale, $key);
        if ($translated === null || $translated === '') {
            return $value;
        }
        if (is_array($value)) {
            $decoded = json_decode($translated, true);

            return is_array($decoded) ? ContentTranslations::mergeBlocks($value, $decoded) : $value;
        }

        return $translated;
    }

    /** @return list<string> */
    public function translatableFields(): array
    {
        return $this->translatable;
    }

    public function translations()
    {
        return $this->morphMany(\App\Models\Translation::class, 'translatable');
    }
}
