<?php

namespace Tests\Feature;

use Tests\TestCase;

/** hikevet:wpress pregateste baza pentru restaurare: prefixul real, setul de caractere declarat, valorile 0x reparate. */
class WpressExtractCommandTest extends TestCase
{
    public function test_restored_sql_declares_utf8mb4_and_puts_the_table_prefix_back(): void
    {
        $dir = sys_get_temp_dir().'/wpress-cmd-'.uniqid();
        mkdir($dir);
        $sql = "CREATE TABLE `SERVMASK_PREFIX_posts` (`id` int);\nINSERT INTO `SERVMASK_PREFIX_posts` VALUES (1,'Castrare câine',0x);\n";
        $file = $dir.'/b.wpress';
        $out = fopen($file, 'wb');
        foreach (['package.json' => json_encode(['Database' => ['Prefix' => 'wp_']]), 'database.sql' => $sql] as $name => $content) {
            fwrite($out, pack('a255a14a12a4088a8', $name, strlen($content), 1700000000, '.', hash('crc32b', $content)));
            fwrite($out, $content);
        }
        fwrite($out, pack('a255a14a4100a8', '', ftell($out), '', 'deadbeef'));
        fclose($out);

        $this->artisan('hikevet:wpress', ['file' => $file, 'target' => $dir.'/x'])->assertSuccessful();
        $restored = file_get_contents($dir.'/x/database.restored.sql');

        $this->assertStringStartsWith("/*!40101 SET NAMES utf8mb4 */;\n", $restored);
        $this->assertStringContainsString("INSERT INTO `wp_posts` VALUES (1,'Castrare câine','');", $restored);
        exec('rm -rf '.escapeshellarg($dir));
    }
}
