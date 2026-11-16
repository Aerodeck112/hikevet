<?php

namespace Tests\Unit;

use App\Services\Import\WpressArchive;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Arhivele sunt construite dupa formatul din sursa All-in-One WP Migration 7.113 (Ai1wm_Archiver / Ai1wm_Compressor). */
class WpressArchiveTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/wpress-test-'.uniqid();
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->dir));
    }

    /** @param array<string,string> $files cale => continut */
    private function build(array $files, ?string $compression = null, array $package = [], bool $v2Eof = true, ?callable $mutateCrc = null): string
    {
        $path = $this->dir.'/b-'.uniqid().'.wpress';
        $out = fopen($path, 'wb');
        $package += ['WordPress' => ['Version' => '7.0.8'], 'Plugin' => ['Version' => '7.113'], 'Database' => ['Prefix' => 'wp_']];
        if ($compression) {
            $package['Compression'] = ['Enabled' => true, 'Type' => $compression];
        }
        $files = ['package.json' => json_encode($package)] + $files;

        foreach ($files as $relative => $content) {
            $name = basename($relative);
            $prefix = dirname($relative) === '.' ? '.' : dirname($relative);
            $body = $content;
            if ($compression && $name !== 'package.json') {
                $body = '';
                foreach (str_split($content, 524288) ?: [] as $chunk) {
                    $packed = $compression === 'gzip' ? gzcompress($chunk) : bzcompress($chunk);
                    $body .= pack('N', strlen($packed)).$packed;
                }
            }
            $crc = hash('crc32b', $content);
            if ($mutateCrc) {
                $crc = $mutateCrc($relative, $crc);
            }
            fwrite($out, pack('a255a14a12a4088a8', $name, strlen($body), 1700000000, $prefix, $crc));
            fwrite($out, $body);
        }
        $size = ftell($out);
        fwrite($out, $v2Eof ? pack('a255a14a4100a8', '', $size, '', 'deadbeef') : pack('a255a14a4100a8', '', '', '', ''));
        fclose($out);

        return $path;
    }

    private function sample(): array
    {
        return [
            'database.sql' => "CREATE TABLE `SERVMASK_PREFIX_hv_st_cases` (id int);\nINSERT INTO `SERVMASK_PREFIX_hv_st_cases` VALUES (1);\n",
            'uploads/2024/03/poza-mare.jpg' => random_bytes(1_300_000),
            'uploads/hikevet-contracte/contract cu spații și diacritice.pdf' => random_bytes(900),
            'uploads/gol.txt' => '',
            'plugins/hikevet-consent-tablet/hikevet-consent-tablet.php' => "<?php // VER 1.5.2\n",
        ];
    }

    public function test_plain_and_compressed_archives_extract_byte_for_byte_with_crc_checked(): void
    {
        foreach ([null, 'gzip', 'bzip2'] as $compression) {
            if ($compression === 'bzip2' && ! function_exists('bzcompress')) {
                continue;
            }
            $files = $this->sample();
            $archive = new WpressArchive($this->build($files, $compression));
            $this->assertSame('wp_', $archive->package()['Database']['Prefix']);
            $this->assertSame($compression, $archive->compression());

            $target = $this->dir.'/out-'.($compression ?? 'plain');
            $stats = $archive->extract($target);

            $this->assertSame(count($files) + 1, $stats['files']);
            $this->assertSame([], $stats['crc_mismatch']);
            $this->assertSame(count($files) + 1, $stats['crc_checked']);
            foreach ($files as $relative => $content) {
                $this->assertSame($content, file_get_contents($target.'/'.$relative), $relative.' ('.($compression ?? 'plain').')');
            }
        }
    }

    public function test_listing_reads_headers_without_content_and_supports_both_end_blocks(): void
    {
        foreach ([true, false] as $v2) {
            $paths = array_column(iterator_to_array((new WpressArchive($this->build($this->sample(), null, [], $v2)))->entries(), false), 'path');
            $this->assertContains('uploads/2024/03/poza-mare.jpg', $paths);
            $this->assertContains('database.sql', $paths);
            $this->assertCount(6, $paths);
        }
    }

    public function test_filter_extracts_only_the_requested_parts(): void
    {
        $stats = (new WpressArchive($this->build($this->sample())))->extract($this->dir.'/db', fn ($p) => $p === 'database.sql');
        $this->assertSame(1, $stats['files']);
        $this->assertFileExists($this->dir.'/db/database.sql');
        $this->assertDirectoryDoesNotExist($this->dir.'/db/uploads');
    }

    public function test_crc_mismatch_is_reported(): void
    {
        $path = $this->build($this->sample(), 'gzip', [], true, fn ($rel, $crc) => $rel === 'uploads/gol.txt' ? 'ffffffff' : $crc);
        $stats = (new WpressArchive($path))->extract($this->dir.'/crc');
        $this->assertSame(['uploads/gol.txt'], $stats['crc_mismatch']);
    }

    public function test_truncated_download_is_detected(): void
    {
        $path = $this->build($this->sample());
        $truncated = $this->dir.'/trunchiat.wpress';
        file_put_contents($truncated, substr(file_get_contents($path), 0, (int) (filesize($path) * 0.6)));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/incomplet|brusc|mijlocul/u');
        (new WpressArchive($truncated))->extract($this->dir.'/t');
    }

    public function test_password_protected_backup_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/parolă/u');
        (new WpressArchive($this->build($this->sample(), null, ['Encrypted' => true, 'EncryptedSignature' => 'x'])))->extract($this->dir.'/e');
    }

    public function test_paths_escaping_the_target_are_refused(): void
    {
        foreach (['../../etc/passwd' => 'x', '/abs/olut.php' => 'y'] as $relative => $content) {
            $path = $this->dir.'/evil-'.md5($relative).'.wpress';
            $out = fopen($path, 'wb');
            fwrite($out, pack('a255a14a12a4088a8', basename($relative), strlen($content), 0, dirname($relative), ''));
            fwrite($out, $content);
            fwrite($out, pack('a255a14a4100a8', '', '', '', ''));
            fclose($out);
            try {
                (new WpressArchive($path))->extract($this->dir.'/safe');
                $this->fail('Calea ar fi trebuit refuzată: '.$relative);
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('nepermisă', $e->getMessage());
            }
        }
        $this->assertFileDoesNotExist($this->dir.'/etc/passwd');
    }
}
