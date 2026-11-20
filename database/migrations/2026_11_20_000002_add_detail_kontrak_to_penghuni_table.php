<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penghuni', function (Blueprint $table) {
            $table->string('jangka_waktu_sewa', 20)->default('tidak_tentu')->after('tanggal_masuk_perkiraan');
            $table->date('tanggal_keluar_rencana')->nullable()->after('jangka_waktu_sewa');
        });
    }

    public function down(): void
    {
        Schema::table('penghuni', function (Blueprint $table) {
            $table->dropColumn(['jangka_waktu_sewa', 'tanggal_keluar_rencana']);
        });
    }
};
