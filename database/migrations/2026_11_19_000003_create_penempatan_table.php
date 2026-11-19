<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penempatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('properti_id')
                ->constrained('properti')
                ->restrictOnDelete();
            $table->foreignId('penghuni_id')
                ->constrained('penghuni')
                ->restrictOnDelete();
            $table->foreignId('kamar_id')
                ->constrained('kamar')
                ->restrictOnDelete();
            $table->date('mulai');
            $table->date('selesai')->nullable();
            $table->string('sebab', 20)->default('masuk');
            $table->foreignId('dibuat_oleh')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->unsignedBigInteger('kamar_terbuka')
                ->nullable()
                ->storedAs('IF(selesai IS NULL, kamar_id, NULL)');
            $table->unsignedBigInteger('penghuni_terbuka')
                ->nullable()
                ->storedAs('IF(selesai IS NULL, penghuni_id, NULL)');
            $table->timestamps();

            $table->unique('kamar_terbuka', 'penempatan_kamar_terbuka_unique');
            $table->unique('penghuni_terbuka', 'penempatan_penghuni_terbuka_unique');
            $table->index(['properti_id', 'penghuni_id', 'mulai']);
            $table->index(['properti_id', 'kamar_id', 'mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penempatan');
    }
};
