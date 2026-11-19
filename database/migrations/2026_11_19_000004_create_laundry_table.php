<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laundry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('properti_id')
                ->constrained('properti')
                ->restrictOnDelete();
            $table->date('tanggal');
            $table->string('jenis', 20)->default('penghuni');
            $table->foreignId('penghuni_id')
                ->nullable()
                ->constrained('penghuni')
                ->restrictOnDelete();
            $table->foreignId('kamar_id')
                ->nullable()
                ->constrained('kamar')
                ->restrictOnDelete();
            $table->decimal('berat_kg', 6, 2)->nullable();
            $table->unsignedInteger('biaya_utama')->nullable();
            $table->boolean('biaya_manual')->default(false);
            $table->string('item_nama', 100)->nullable();
            $table->unsignedSmallInteger('item_jumlah')->nullable();
            $table->decimal('item_berat_kg', 6, 2)->nullable();
            $table->unsignedInteger('item_biaya')->nullable();
            $table->text('catatan')->nullable();
            $table->string('ref', 100)->nullable()->unique();
            $table->foreignId('dicatat_oleh')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['properti_id', 'penghuni_id', 'tanggal']);
            $table->index(['properti_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laundry');
    }
};
