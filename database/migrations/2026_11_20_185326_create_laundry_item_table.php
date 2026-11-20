<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pecah item terpisah menjadi tabel sendiri agar satu catatan laundry dapat memuat banyak item
     * (mis. selimut + bed cover). Kolom laundry.item_biaya dipertahankan sebagai total bayar terpisah.
     */
    public function up(): void
    {
        Schema::create('laundry_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laundry_id')
                ->constrained('laundry')
                ->cascadeOnDelete();
            $table->string('nama', 100);
            $table->unsignedSmallInteger('jumlah')->default(1);
            $table->decimal('berat_kg', 6, 2)->nullable();
            $table->unsignedInteger('biaya')->default(0);
            $table->unsignedTinyInteger('urutan')->default(0);
            $table->timestamps();

            $table->index(['laundry_id', 'urutan']);
        });

        DB::table('laundry')
            ->where(function ($query): void {
                $query->whereNotNull('item_nama')->orWhere('item_biaya', '>', 0);
            })
            ->orderBy('id')
            ->chunkById(200, function ($daftar): void {
                $baris = [];

                foreach ($daftar as $laundry) {
                    $baris[] = [
                        'laundry_id' => $laundry->id,
                        'nama' => $laundry->item_nama ?: 'Item terpisah',
                        'jumlah' => max(1, (int) ($laundry->item_jumlah ?? 1)),
                        'berat_kg' => $laundry->item_berat_kg,
                        'biaya' => (int) ($laundry->item_biaya ?? 0),
                        'urutan' => 0,
                        'created_at' => $laundry->created_at,
                        'updated_at' => $laundry->updated_at,
                    ];
                }

                if ($baris !== []) {
                    DB::table('laundry_item')->insert($baris);
                }
            });

        Schema::table('laundry', function (Blueprint $table) {
            $table->dropColumn(['item_nama', 'item_jumlah', 'item_berat_kg']);
        });
    }

    public function down(): void
    {
        Schema::table('laundry', function (Blueprint $table) {
            $table->string('item_nama', 100)->nullable()->after('biaya_manual');
            $table->unsignedSmallInteger('item_jumlah')->nullable()->after('item_nama');
            $table->decimal('item_berat_kg', 6, 2)->nullable()->after('item_jumlah');
        });

        $itemPertama = DB::table('laundry_item')
            ->select('laundry_id', DB::raw('MIN(id) AS id_pertama'), DB::raw('COUNT(*) AS jumlah_item'))
            ->groupBy('laundry_id')
            ->get();

        foreach ($itemPertama as $ringkas) {
            $item = DB::table('laundry_item')->where('id', $ringkas->id_pertama)->first();

            if ($item === null) {
                continue;
            }

            DB::table('laundry')->where('id', $ringkas->laundry_id)->update([
                'item_nama' => (int) $ringkas->jumlah_item > 1 ? $item->nama.' (+'.((int) $ringkas->jumlah_item - 1).')' : $item->nama,
                'item_jumlah' => $item->jumlah,
                'item_berat_kg' => $item->berat_kg,
            ]);
        }

        Schema::dropIfExists('laundry_item');
    }
};
