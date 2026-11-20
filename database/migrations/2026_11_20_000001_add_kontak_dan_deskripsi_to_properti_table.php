<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properti', function (Blueprint $table) {
            $table->string('no_wa_admin', 30)->nullable()->after('alamat');
            $table->text('deskripsi')->nullable()->after('no_wa_admin');
        });
    }

    public function down(): void
    {
        Schema::table('properti', function (Blueprint $table) {
            $table->dropColumn(['no_wa_admin', 'deskripsi']);
        });
    }
};
