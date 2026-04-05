<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrap(); // Menggunakan Bootstrap untuk pagination 
        View::composer('*', function ($view) { // Mengambil data kategori dari database dan mengirimkannya ke semua view
        $kategori = DB::table('kategori')->orderBy('nama_kategori', 'asc')->get(); // Mengambil data kategori dari database dan mengurutkannya berdasarkan nama_kategori secara ascending
        $view->with('kategori', $kategori); // Mengirim data kategori ke semua view dengan nama variabel 'kategori'
        });

    }
}
