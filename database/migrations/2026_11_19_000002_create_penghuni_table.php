<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penghuni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('properti_id')
                ->constrained('properti')
                ->restrictOnDelete();
            $table->string('nama', 120);
            $table->string('alias', 120)->nullable();
            $table->string('no_wa', 30)->nullable();
            $table->string('status', 20)->default('aktif');
            $table->date('tanggal_masuk');
            $table->boolean('tanggal_masuk_perkiraan')->default(false);
            $table->date('tanggal_keluar')->nullable();
            $table->string('alasan_keluar', 255)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['properti_id', 'status']);
            $table->index(['properti_id', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penghuni');
    }
};
