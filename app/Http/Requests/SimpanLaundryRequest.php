<?php

namespace App\Http\Requests;

use App\Models\Laundry;
use App\Models\LaundryItem;
use App\Models\Properti;
use App\Services\HargaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SimpanLaundryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $properti = $this->propertiAktif();

        if ($user === null || $properti === null) {
            return false;
        }

        $laundry = $this->route('laundry');

        if ($laundry !== null) {
            return $laundry instanceof Laundry
                && $laundry->properti_id === $properti->id
                && $user->can('update', $laundry);
        }

        return $user->can('create', [Laundry::class, $properti]);
    }

    protected function prepareForValidation(): void
    {
        $jenis = (string) $this->input('jenis', 'penghuni');
        $berat = $this->normalisasiDesimal($this->input('berat_kg'));

        $biayaRaw = $this->input('biaya_utama');
        $biaya = $biayaRaw !== null && trim((string) $biayaRaw) !== ''
            ? trim((string) $biayaRaw)
            : null;

        $itemInput = $this->input('item');
        $itemBersih = [];

        if (is_array($itemInput)) {
            foreach ($itemInput as $baris) {
                if (! is_array($baris)) {
                    continue;
                }

                $nama = trim((string) ($baris['nama'] ?? ''));
                $biayaItemRaw = trim((string) ($baris['biaya'] ?? ''));
                $beratItem = $this->normalisasiDesimal($baris['berat_kg'] ?? null);
                $jumlahRaw = trim((string) ($baris['jumlah'] ?? ''));

                if ($nama === '' && $biayaItemRaw === '' && $beratItem === null) {
                    continue;
                }

                $itemBersih[] = [
                    'nama' => $nama,
                    'jumlah' => $jumlahRaw !== '' ? $jumlahRaw : '1',
                    'berat_kg' => $beratItem,
                    'biaya' => $biayaItemRaw,
                ];
            }
        }

        $this->merge([
            'jenis' => $jenis,
            'penghuni_id' => $jenis === 'penghuni' && trim((string) $this->input('penghuni_id', '')) !== ''
                ? $this->input('penghuni_id')
                : null,
            'berat_kg' => $berat,
            'biaya_utama' => $biaya,
            'item' => $itemBersih,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $properti = $this->propertiAktif();
        $hariIni = now('Asia/Jakarta')->toDateString();

        return [
            'jenis' => ['required', Rule::in(array_keys(Laundry::DAFTAR_JENIS))],
            'tanggal' => ['required', 'date', "before_or_equal:{$hariIni}"],
            'penghuni_id' => [
                'nullable',
                'required_if:jenis,penghuni',
                'integer',
                Rule::exists('penghuni', 'id')->where(
                    fn ($query) => $query->where('properti_id', $properti?->id)->whereNull('deleted_at')
                ),
            ],
            'berat_kg' => ['nullable', 'numeric', 'gt:0', 'max:99.99', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'konfirmasi_berat_besar' => ['sometimes', 'boolean'],
            'biaya_utama' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'ref' => ['nullable', 'string', 'max:100'],
            'item' => ['nullable', 'array', 'max:'.LaundryItem::MAKS_PER_CATATAN],
            'item.*.nama' => ['required', 'string', 'max:100'],
            'item.*.jumlah' => ['required', 'integer', 'min:1', 'max:99'],
            'item.*.berat_kg' => ['nullable', 'numeric', 'gt:0', 'max:99.99', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'item.*.biaya' => ['required', 'integer', 'min:0', 'max:10000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'jenis.required' => 'Jenis catatan laundry belum dipilih.',
            'jenis.in' => 'Pilihan jenis catatan laundry tidak valid.',
            'tanggal.required' => 'Tanggal laundry belum diisi.',
            'tanggal.date' => 'Format tanggal laundry tidak valid.',
            'tanggal.before_or_equal' => 'Tanggal laundry tidak boleh di masa depan.',
            'penghuni_id.required_if' => 'Penghuni belum dipilih untuk catatan laundry penghuni.',
            'penghuni_id.integer' => 'Pilihan penghuni tidak valid.',
            'penghuni_id.exists' => 'Penghuni tidak ditemukan atau bukan milik properti ini.',
            'berat_kg.numeric' => 'Berat cucian utama harus berupa angka.',
            'berat_kg.gt' => 'Berat cucian utama harus lebih dari 0 kg.',
            'berat_kg.max' => 'Berat cucian utama maksimal 99,99 kg.',
            'berat_kg.regex' => 'Berat cucian utama maksimal 2 angka di belakang koma (contoh: 2,4).',
            'biaya_utama.integer' => 'Biaya utama harus berupa angka bulat rupiah.',
            'biaya_utama.min' => 'Biaya utama tidak boleh negatif.',
            'biaya_utama.max' => 'Biaya utama maksimal Rp10.000.000.',
            'catatan.max' => 'Catatan maksimal 500 karakter.',
            'item.max' => 'Maksimal '.LaundryItem::MAKS_PER_CATATAN.' item terpisah dalam satu catatan.',
            'item.*.nama.required' => 'Nama item terpisah belum diisi.',
            'item.*.nama.max' => 'Nama item terpisah maksimal 100 karakter.',
            'item.*.jumlah.required' => 'Jumlah item terpisah belum diisi.',
            'item.*.jumlah.integer' => 'Jumlah item terpisah harus berupa angka bulat.',
            'item.*.jumlah.min' => 'Jumlah item terpisah minimal 1.',
            'item.*.jumlah.max' => 'Jumlah item terpisah maksimal 99.',
            'item.*.berat_kg.numeric' => 'Berat item terpisah harus berupa angka.',
            'item.*.berat_kg.gt' => 'Berat item terpisah harus lebih dari 0 kg.',
            'item.*.berat_kg.max' => 'Berat item terpisah maksimal 99,99 kg.',
            'item.*.berat_kg.regex' => 'Berat item terpisah maksimal 2 angka di belakang koma.',
            'item.*.biaya.required' => 'Biaya item terpisah belum diisi.',
            'item.*.biaya.integer' => 'Biaya item terpisah harus berupa angka bulat rupiah.',
            'item.*.biaya.min' => 'Biaya item terpisah tidak boleh negatif.',
            'item.*.biaya.max' => 'Biaya item terpisah maksimal Rp10.000.000.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $berat = $this->input('berat_kg');
                $item = $this->input('item', []);

                if (($berat === null || $berat === '') && ($item === [] || $item === null)) {
                    $validator->errors()->add(
                        'berat_kg',
                        'Isi berat cucian utama atau tambahkan minimal satu item terpisah.'
                    );

                    return;
                }

                if ($berat !== null
                    && $berat !== ''
                    && ! $validator->errors()->has('berat_kg')
                    && is_numeric($berat)
                    && app(HargaService::class)->keCentiKg((string) $berat) > 3000
                    && ! $this->boolean('konfirmasi_berat_besar')) {
                    $validator->errors()->add(
                        'konfirmasi_berat_besar',
                        'Berat melebihi 30 kg. Centang "Saya yakin berat cucian ini benar" untuk melanjutkan.'
                    );
                }
            },
        ];
    }

    private function normalisasiDesimal(mixed $nilai): ?string
    {
        if ($nilai === null) {
            return null;
        }

        $teks = str_replace(',', '.', trim((string) $nilai));

        return $teks !== '' ? $teks : null;
    }

    private function propertiAktif(): ?Properti
    {
        $properti = app()->bound('properti.aktif') ? app('properti.aktif') : null;

        return $properti instanceof Properti ? $properti : null;
    }
}
