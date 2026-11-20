<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Fotografia scoasă la cererea clinicii dispare și din conținutul unui site deja instalat. */
class RemovedPhotoMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_installed_content_no_longer_references_the_removed_photo(): void
    {
        $path = 'media/2023/05/0h7a9586-scaled-e1689027689830.webp';
        DB::table('pages')->insert(['slug' => 'home-proba', 'title' => 'Acasă', 'template' => 'home', 'status' => 'published',
            'blocks' => json_encode(['hero_title' => 'Titlu', 'staff_image' => $path]), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('media')->insert(['path' => $path, 'created_at' => now(), 'updated_at' => now()]);

        (require database_path('migrations/2026_11_20_000001_remove_staff_hug_photo.php'))->up();

        $blocks = json_decode(DB::table('pages')->where('slug', 'home-proba')->value('blocks'), true);
        $this->assertSame(['hero_title' => 'Titlu'], $blocks);
        $this->assertSame(0, DB::table('media')->where('path', $path)->count());
        $this->get('/')->assertDontSee('0h7a9586');
        $this->assertFileDoesNotExist(public_path($path));
    }
}
