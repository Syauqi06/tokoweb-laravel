<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Produk;
use App\Models\Order;
use App\Models\User;

class BerandaController extends Controller
{
    public function berandaBackend()
    {
        return view('backend.v_beranda.index', [
            'judul'         => 'Beranda',
            'sub'           => 'Halaman Beranda',
            'totalUsers'    => User::count(),
            'newUsers'      => User::whereMonth('created_at', now()->month)->count(),
            'totalShop'     => Produk::count(),
            'totalOrders'   => Order::count(),
            'pendingOrders' => Order::where('status', 'pending')->count(),
            'onlineOrders'  => Order::whereIn('status', ['Paid', 'Kirim', 'Selesai'])->count(),
        ]);
    }

    public function index()
    {
        $produk = Produk::where('status', 1)->orderBy('updated_at', 'desc')->paginate(6);
        return view('v_beranda.index', [
            'judul' => 'Halaman Beranda',
            'produk' => $produk,
        ]);
    }
}
