{{--
    419 adalah error yang paling sering ditemui pengguna nyata, dan yang paling
    membingungkan bila tidak dijelaskan: token CSRF kedaluwarsa setelah halaman
    dibiarkan terbuka lama. Pesan bawaan Laravel ("Page Expired") tidak memberi
    tahu apa yang harus dilakukan, sehingga pengguna menyangka aplikasinya rusak.
--}}
@extends('errors.layout')

@section('kode', '419')
@section('judul', 'Sesi Anda sudah kedaluwarsa')
@section('penjelasan', 'Halaman ini dibiarkan terbuka terlalu lama, jadi token keamanannya tidak berlaku lagi. Data yang Anda kirim tidak tersimpan.')
@section('petunjuk', 'Buka kembali halamannya dari awal, lalu isi dan kirim sekali lagi. Ini pengamanan biasa, bukan kerusakan.')
