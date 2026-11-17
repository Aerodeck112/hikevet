<?php

use Database\Seeders\RolesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arhiva bruta a bazei WordPress: fiecare tabela si fiecare rand, criptate, plus copia criptata a fisierului SQL.
 * Pastreaza si datele care nu au un ecran al lor in platforma noua, dupa ce site-ul vechi este oprit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_tables', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->json('columns');
            $table->string('primary_key', 64)->nullable();
            $table->unsignedBigInteger('row_count')->default(0);
            $table->char('sha256', 64)->nullable();
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('snapshotted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('legacy_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legacy_table_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('ordinal');
            $table->string('pk', 191)->nullable();
            $table->longText('data');
            $table->char('sha256', 64);
            $table->index(['legacy_table_id', 'ordinal']);
            $table->index(['legacy_table_id', 'pk']);
        });

        Schema::create('legacy_files', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30);
            $table->text('label');
            $table->string('path');
            $table->unsignedBigInteger('bytes');
            $table->char('sha256', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Permisiunile noi (legacy.view, legacy.export) pentru instalarile existente.
        if (Schema::hasTable('roles')) {
            (new RolesSeeder)->run();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_rows');
        Schema::dropIfExists('legacy_tables');
        Schema::dropIfExists('legacy_files');
    }
};
