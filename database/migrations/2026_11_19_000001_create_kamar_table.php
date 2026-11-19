<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kamar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('properti_id')
                ->constrained('properti')
                ->restrictOnDelete();
            $table->string('nomor', 20);
            $table->unsignedTinyInteger('lantai')->default(1);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique(['properti_id', 'nomor'], 'kamar_properti_nomor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kamar');
    }
};
