<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pesan Reset Kata Sandi
    |--------------------------------------------------------------------------
    |
    | Tanpa berkas ini Laravel jatuh ke pesan bawaan berbahasa Inggris, karena
    | APP_FALLBACK_LOCALE=en. Pesan 'token' sengaja tidak berhenti pada "tidak
    | valid" — penyebab paling sering adalah pengguna membuka tautan lama
    | setelah meminta tautan baru, dan itu tidak bisa ditebak sendiri.
    |
    */

    'reset' => 'Kata sandi Anda berhasil diubah.',
    'sent' => 'Tautan untuk mengatur ulang kata sandi sudah dikirim ke email Anda.',
    'throttled' => 'Terlalu sering mencoba. Mohon tunggu sebentar sebelum meminta lagi.',
    'token' => 'Tautan ini sudah tidak berlaku. Tautan reset hanya berlaku 60 menit, dan meminta tautan baru otomatis membatalkan tautan sebelumnya — pastikan Anda membuka email yang paling terakhir masuk. Silakan minta tautan baru di bawah.',
    'user' => 'Kami tidak menemukan pengguna dengan alamat email tersebut.',

];
