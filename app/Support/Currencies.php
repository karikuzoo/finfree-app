<?php

namespace App\Support;

/**
 * Mata uang yang bisa dipilih untuk rekening valas — kode ISO 4217 beserta
 * nama Indonesianya. Daftar tetap, bukan isian bebas: kodenya ditampilkan di
 * kartu sebagai satuan ("1.500 USD"), jadi salah ketik ("UDS") langsung
 * merusak keterangan itu.
 *
 * Urutannya kira-kira menurut seberapa sering dipegang orang Indonesia —
 * dolar AS dulu, lalu tujuan kerja, belajar, umrah, dan wisata yang umum.
 */
final class Currencies
{
    public const LIST = [
        'USD' => 'Dolar Amerika Serikat',
        'SGD' => 'Dolar Singapura',
        'SAR' => 'Riyal Arab Saudi',
        'JPY' => 'Yen Jepang',
        'EUR' => 'Euro',
        'AUD' => 'Dolar Australia',
        'MYR' => 'Ringgit Malaysia',
        'CNY' => 'Yuan Tiongkok',
        'GBP' => 'Pound Sterling Inggris',
        'HKD' => 'Dolar Hong Kong',
        'KRW' => 'Won Korea Selatan',
        'TWD' => 'Dolar Taiwan',
        'THB' => 'Baht Thailand',
        'AED' => 'Dirham Uni Emirat Arab',
        'CHF' => 'Franc Swiss',
        'NZD' => 'Dolar Selandia Baru',
        'CAD' => 'Dolar Kanada',
    ];

    /** @return array<int, string> */
    public static function codes(): array
    {
        return array_keys(self::LIST);
    }

    /** Untuk pilihan di form: [{code, label}, …]. */
    public static function options(): array
    {
        return array_map(
            fn (string $kode, string $nama) => ['code' => $kode, 'label' => $nama],
            array_keys(self::LIST),
            self::LIST,
        );
    }
}
