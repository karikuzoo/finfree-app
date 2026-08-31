{{--
    Dipicu oleh pembatas laju pada endpoint tamu (PRD FR-40): masuk, daftar,
    dan permintaan reset kata sandi dibatasi 5 percobaan per menit.
--}}
@extends('errors.layout')

@section('kode', '429')
@section('judul', 'Terlalu banyak percobaan')
@section('penjelasan', 'Permintaan dari perangkat ini dibatasi sementara untuk menjaga keamanan akun.')
@section('petunjuk', 'Tunggu sekitar satu menit, lalu coba lagi. Batas ini otomatis hilang dengan sendirinya.')
