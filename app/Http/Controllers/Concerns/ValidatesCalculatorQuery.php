<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Validasi untuk kalkulator publik yang menerima isiannya lewat QUERY STRING
 * (GET), bukan kiriman formulir.
 *
 * Kalau validasi gagal, JANGAN kembali ke "halaman sebelumnya" seperti
 * perilaku bawaan Laravel. Untuk halaman GET, halaman sebelumnya bisa jadi
 * adalah alamat yang sama persis: memuat ulang tautan yang isiannya tidak
 * lagi sah (mis. `rate_type=fix_float` sesudah pilihan itu dilebur ke
 * berjenjang) membuat Laravel menyimpannya sebagai halaman sebelumnya, lalu
 * pemuatan berikutnya gagal dan diarahkan ke alamat itu lagi — berputar
 * sampai browser menyerah. Pada 30 Sep 2026 putaran ini menghabiskan batas
 * 60 permintaan/menit dalam beberapa detik dan pengguna melihat halaman 429.
 *
 * Sebagai gantinya, arahkan ke alamat kalkulator yang BERSIH (tanpa query)
 * beserta pesan galatnya. Alamat bersih selalu berhasil dimuat, jadi putaran
 * mustahil. Kiriman dari form (Inertia, `preserveState`) tetap mempertahankan
 * isian di layar; yang hilang hanya query di bilah alamat.
 */
trait ValidatesCalculatorQuery
{
    /** @return array<string, mixed> */
    protected function validateCalculatorQuery(Request $request, string $routeName, array $rules, array $messages = []): array
    {
        $validator = validator($request->query(), $rules, $messages);

        if ($validator->fails()) {
            throw (new ValidationException($validator))->redirectTo(route($routeName));
        }

        return $validator->validated();
    }

    /** Galat yang ditemukan sesudah validasi aturan biasa — ke alamat bersih juga. */
    protected function failCalculatorQuery(string $routeName, array $messages): never
    {
        throw ValidationException::withMessages($messages)->redirectTo(route($routeName));
    }
}
