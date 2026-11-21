<?php

namespace App\Services\Seo;

use App\Support\Settings;

/** Metadatele unei pagini: titlu, descriere, canonical, Open Graph si JSON-LD. */
class Seo
{
    public string $title;

    public string $description;

    public string $canonical;

    public ?string $image = null;

    public bool $noindex = false;

    public string $type = 'website';

    /** @var list<array> */
    public array $jsonLd = [];

    /** @var list<array{name:string,url:string}> */
    public array $breadcrumbs = [];

    public function __construct()
    {
        $this->title = __(':name – Clinică veterinară în :city', ['name' => Settings::get('clinic.name'), 'city' => __((string) Settings::get('clinic.city'))]);
        $this->description = __((string) Settings::get('seo.default_description'));
        $this->canonical = self::canonicalUrl(request()->path());
    }

    /** Adresa canonica in limba data (implicit limba curenta): romana la radacina, celelalte cu /en sau /hu. */
    public static function canonicalUrl(string $path, ?string $locale = null): string
    {
        $path = trim($path, '/');

        return rtrim(config('app.url'), '/').\App\Support\Locales::prefix($locale).'/'.($path === '' ? '' : $path.'/');
    }

    /** Variantele paginii curente in toate limbile, pentru hreflang. @return array<string, string> */
    public function alternates(): array
    {
        $path = request()->path();

        return collect(\App\Support\Locales::ALL)->mapWithKeys(fn ($l, $code) => [$l['hreflang'] => self::canonicalUrl($path, $code)])->all();
    }

    public function page(string $title, ?string $description = null, ?string $image = null): static
    {
        $this->title = $title.' – '.Settings::get('clinic.name');
        if ($description) {
            $this->description = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($description))), 0, 160);
        }
        if ($image) {
            $this->image = asset($image);
        }

        return $this;
    }

    /** @param list<array{0:string,1:string}> $items */
    public function crumbs(array $items): static
    {
        $this->breadcrumbs = array_map(fn ($i) => ['name' => $i[0], 'url' => self::canonicalUrl($i[1])], $items);
        $this->jsonLd[] = [
            '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c['name'], 'item' => $c['url']],
                $this->breadcrumbs, array_keys($this->breadcrumbs)),
        ];

        return $this;
    }

    public function schema(array $schema): static
    {
        $this->jsonLd[] = ['@context' => 'https://schema.org'] + $schema;

        return $this;
    }

    /** Schema clinicii, doar cu date confirmate (fara program sau rating inventate). */
    public static function clinicSchema(): array
    {
        $schema = [
            '@type' => 'VeterinaryCare', '@id' => rtrim(config('app.url'), '/').'/#clinic',
            'name' => Settings::get('clinic.name'), 'url' => rtrim(config('app.url'), '/').'/',
            'telephone' => Settings::get('clinic.phone'), 'email' => Settings::get('clinic.email'),
            'address' => ['@type' => 'PostalAddress', 'streetAddress' => Settings::get('clinic.address'),
                'addressLocality' => Settings::get('clinic.city'), 'addressRegion' => 'Mureș', 'addressCountry' => 'RO'],
            'image' => asset('media/2023/05/0h7a9513.webp'),
        ];

        $hours = array_values(array_filter((array) Settings::get('clinic.hours', [])));
        if ($hours) {
            $schema['openingHours'] = $hours;
        }

        $same = array_values(array_filter([Settings::get('clinic.instagram'), Settings::get('clinic.facebook'), Settings::get('clinic.tiktok')]));
        if ($same) {
            $schema['sameAs'] = $same;
        }

        return $schema;
    }
}
