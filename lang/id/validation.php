<?php

/**
 * Pesan validasi Bahasa Indonesia.
 *
 * Hanya memuat aturan yang benar-benar dipakai aplikasi ini. Kunci yang tidak
 * ada di sini otomatis jatuh ke Bahasa Inggris lewat APP_FALLBACK_LOCALE —
 * jadi kalau suatu saat muncul pesan berbahasa Inggris di layar, artinya ada
 * aturan baru yang perlu ditambahkan ke berkas ini.
 *
 * Adanya berkas ini membuat controller tidak perlu lagi menuliskan array
 * `$messages` sendiri untuk aturan umum. Tulis pesan khusus di controller hanya
 * bila kalimatnya memang perlu lebih spesifik daripada versi umum di sini.
 */
return [
    'required' => ':attribute wajib diisi.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'email' => 'Format :attribute tidak valid.',
    'lowercase' => ':attribute harus ditulis dengan huruf kecil.',
    'unique' => ':attribute ini sudah terdaftar.',
    'current_password' => 'Kata sandi yang dimasukkan salah.',
    'regex' => 'Format :attribute tidak sesuai.',
    'boolean' => ':attribute harus berupa ya atau tidak.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'numeric' => ':attribute harus berupa angka.',
    'image' => ':attribute harus berupa gambar.',
    'date' => ':attribute bukan tanggal yang valid.',
    'in' => ':attribute yang dipilih tidak dikenali.',
    'enum' => ':attribute yang dipilih tidak dikenali.',
    'mimes' => ':attribute harus berformat: :values.',
    'dimensions' => 'Ukuran :attribute melampaui batas yang diizinkan.',
    'uploaded' => ':attribute gagal diunggah. Kemungkinan ukurannya melebihi batas server.',

    'min' => [
        'numeric' => ':attribute minimal :min.',
        'string' => ':attribute minimal :min karakter.',
        'file' => 'Ukuran :attribute minimal :min kilobyte.',
        'array' => ':attribute minimal berisi :min item.',
    ],

    'max' => [
        'numeric' => ':attribute maksimal :max.',
        'string' => ':attribute maksimal :max karakter.',
        'file' => 'Ukuran :attribute maksimal :max kilobyte.',
        'array' => ':attribute maksimal berisi :max item.',
    ],

    // Sub-pesan aturan Illuminate\Validation\Rules\Password.
    // Aturannya sendiri disetel sekali di AppServiceProvider.
    'password' => [
        'letters' => ':attribute harus memuat minimal satu huruf.',
        'mixed' => ':attribute harus memuat huruf besar dan huruf kecil.',
        'numbers' => ':attribute harus memuat minimal satu angka.',
        'symbols' => ':attribute harus memuat minimal satu simbol, misalnya ! @ # $ %.',
        'uncompromised' => ':attribute ini pernah bocor di kebocoran data. Pilih yang lain.',
    ],

    /**
     * Nama field dalam Bahasa Indonesia, dipakai menggantikan :attribute.
     * Tanpa ini pesannya berbunyi "password wajib diisi" alih-alih
     * "Kata sandi wajib diisi".
     */
    'attributes' => [
        'name' => 'Nama',
        'email' => 'Email',
        'password' => 'Kata sandi',
        'password_confirmation' => 'Konfirmasi kata sandi',
        'current_password' => 'Kata sandi saat ini',
        'avatar' => 'Foto profil',
        'risk_profile' => 'Profil risiko',
        'prefers_syariah' => 'Preferensi syariah',
        'target_amount' => 'Nominal target',
        'current_amount' => 'Dana yang sudah dimiliki',
        'months' => 'Jangka waktu',
        'annual_return_rate' => 'Estimasi imbal hasil',
        'annual_inflation_rate' => 'Estimasi inflasi',
    ],
];
