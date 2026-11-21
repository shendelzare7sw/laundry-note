<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laundry', function (Blueprint $table): void {
            $table->string('nama_tanpa_nama', 120)->nullable()->after('kamar_id');
        });
    }

    public function down(): void
    {
        Schema::table('laundry', function (Blueprint $table): void {
            $table->dropColumn('nama_tanpa_nama');
        });
    }
};
