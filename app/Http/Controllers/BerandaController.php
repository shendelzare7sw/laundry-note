<?php

namespace App\Http\Controllers;

use App\Models\Properti;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BerandaController extends Controller
{
    public function index(Request $request): View
    {
        $propertiAktif = app()->bound('properti.aktif')
            ? app('properti.aktif')
            : null;

        return view('beranda.index', [
            'propertiAktif' => $propertiAktif instanceof Properti ? $propertiAktif : null,
        ]);
    }
}
