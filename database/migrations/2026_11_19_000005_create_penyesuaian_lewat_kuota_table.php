<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penyesuaian_lewat_kuota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('properti_id')
                ->constrained('properti')
                ->restrictOnDelete();
            $table->foreignId('penghuni_id')
                ->constrained('penghuni')
                ->restrictOnDelete();
            $table->char('bulan', 7);
            $table->unsignedInteger('nominal');
            $table->string('catatan', 255)->nullable();
            $table->foreignId('oleh')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['penghuni_id', 'bulan'], 'penyesuaian_penghuni_bulan_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penyesuaian_lewat_kuota');
    }
};
