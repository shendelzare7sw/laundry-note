<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('properti_id')
                ->nullable()
                ->constrained('properti')
                ->restrictOnDelete();
            $table->string('kunci', 64);
            $table->text('nilai')->nullable();
            $table->date('berlaku_mulai');
            $table->foreignId('diubah_oleh')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->unsignedBigInteger('properti_kunci')
                ->storedAs('COALESCE(properti_id, 0)');
            $table->timestamps();

            $table->unique(
                ['properti_kunci', 'kunci', 'berlaku_mulai'],
                'pengaturan_properti_kunci_berlaku_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan');
    }
};
