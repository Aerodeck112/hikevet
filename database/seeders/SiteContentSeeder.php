<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\TeamMember;
use App\Support\Settings;
use Illuminate\Database\Seeder;

/**
 * Continutul public preluat din hikevet.com la 12.11.2026 (texte, imagini, URL-uri).
 * Idempotent: nu suprascrie nimic din ce a fost deja editat in admin.
 */
class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('seeders/data/site-content.json')), true);
        $manifest = json_decode(file_get_contents(database_path('seeders/data/media-manifest.json')), true);

        foreach ($manifest as $slug => $entry) {
            $large = $entry['variants']['large'];
            Media::firstOrCreate(['path' => $large['path']], [
                'disk' => 'site', 'original_name' => basename($entry['source']), 'mime' => 'image/webp',
                'size' => $large['bytes'], 'width' => $large['width'], 'height' => $large['height'],
                'category' => str_contains($slug, '2023/08') ? 'parteneri' : 'site',
                'sha256' => $entry['sha256_source'], 'source_url' => 'https://hikevet.com/wp-content/uploads/'.$entry['source'],
                'license_note' => preg_match('/pexels|utc/', $slug) ? 'Imagine stock de pe site-ul vechi: licența trebuie confirmată' : null,
            ]);
        }

        $img = fn (?string $slug) => $slug ? 'media/'.$slug.'.webp' : null;

        $categories = [];
        foreach ($data['service_categories'] as $c) {
            $categories[$c['slug']] = ServiceCategory::firstOrCreate(['slug' => $c['slug']], ['name' => $c['name'], 'sort' => $c['sort']])->id;
        }

        foreach ($data['services'] as $s) {
            Service::firstOrCreate(['slug' => $s['slug']], [
                'service_category_id' => $categories[$s['category']] ?? null, 'name' => $s['name'], 'excerpt' => $s['excerpt'],
                'body' => $s['body'], 'icon' => $s['icon'], 'booking_url' => $s['booking_url'] ?: null, 'sort' => $s['sort'],
                'is_published' => true, 'duration_minutes' => 30,
            ]);
        }

        foreach ($data['team'] as $t) {
            TeamMember::firstOrCreate(['slug' => $t['slug']], [
                'name' => $t['name'], 'role_title' => $t['role_title'], 'bio' => $t['bio'], 'photo' => $img($t['photo']), 'sort' => $t['sort'],
            ]);
        }

        foreach ($data['posts'] as $p) {
            Post::firstOrCreate(['slug' => $p['slug']], [
                'title' => $p['title'], 'excerpt' => $p['excerpt'], 'body' => $p['body'], 'image' => $img($p['image']),
                'status' => 'published', 'published_at' => $p['published_at'] ?? now(), 'author_name' => 'HiKeVet',
            ]);
        }

        foreach ($data['faqs'] as $i => $f) {
            Faq::firstOrCreate(['question' => $f['question']], ['answer' => $f['answer'], 'sort' => $i]);
        }

        foreach ($data['pages'] as $p) {
            $blocks = $p['blocks'] ?? [];
            foreach (['hero_image', 'staff_image', 'subscription_image', 'image'] as $key) {
                if (isset($blocks[$key])) {
                    $blocks[$key] = $img($blocks[$key]);
                }
            }
            if ($p['slug'] === 'home') {
                $blocks['partners'] = array_map(fn ($x) => ['image' => $img($x['image']), 'alt' => $x['alt']], $data['partners']);
                $blocks['instagram'] = array_map($img, $data['instagram']);
            }

            Page::firstOrCreate(['slug' => $p['slug']], [
                'title' => $p['title'], 'template' => $p['template'], 'status' => $p['status'] ?? 'published',
                'published_at' => now(), 'lead' => $p['lead'] ?? null, 'body' => $p['body'] ?? null, 'blocks' => $blocks ?: null,
                'hero_image' => $img($p['hero_image'] ?? null), 'seo_title' => $p['seo_title'] ?? null,
                'noindex' => $p['noindex'] ?? false, 'document_version' => $p['document_version'] ?? null,
            ]);
        }

        foreach ($data['redirects'] as $r) {
            Redirect::firstOrCreate(['from_path' => $r['from']], ['to_path' => $r['to'], 'status_code' => 301, 'note' => $r['note']]);
        }

        if (Settings::get('clinic.instagram') === '') {
            Settings::set('clinic.instagram', 'https://www.instagram.com/clinica_veterinara_hikevet/');
        }
    }
}
