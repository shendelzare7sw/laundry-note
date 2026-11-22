@extends('errors.layout')

@section('kode', '403')
@section('ikon', 'fa-lock')
@section('judul', 'Akses Ditolak')
@section('pesan', $exception->getMessage() ?: 'Anda tidak memiliki izin untuk membuka halaman atau melakukan tindakan ini.')
