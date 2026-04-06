<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RajaOngkirController extends Controller
{
    public function index()
    {
        return view('ongkir');
    }

    public function getProvinces()
    {
        $response = Http::withHeaders([
            'key'    => env('RAJAONGKIR_API_KEY'),
            'Accept' => 'application/json',
        ])->get(env('RAJAONGKIR_BASE_URL') . '/province');

        // Ambil hanya bagian 'data' saja
        return response()->json($response->json()['data'] ?? []);
    }

    public function getCities($provinceId)
    {
        $response = Http::withHeaders([
            'key'    => env('RAJAONGKIR_API_KEY'),
            'Accept' => 'application/json',
        ])->get(env('RAJAONGKIR_BASE_URL') . '/city/' . $provinceId);

        return response()->json($response->json()['data'] ?? []);
    }

    public function getCost(Request $request)
    {
        $response = Http::asForm()->withHeaders([
            'key'    => env('RAJAONGKIR_API_KEY'),
            'Accept' => 'application/json',
        ])->post(env('RAJAONGKIR_CALCULATE_URL') . '/domestic-cost', [ // endpoint untuk menghitung ongkir
            'origin'      => $request->input('origin'), // ID kota asal didapat dari select_shipping.blade.php yang dikirim melalui AJAX
            'destination' => $request->input('destination'), // ID Kota tujuan (pembeli)
            'weight'      => (int) $request->input('weight'),
            'courier'     => $request->input('courier'),
        ]);

        return response()->json($response->json()['data'] ?? []); // Ambil hanya bagian 'data' saja untuk dikirim kembali ke AJAX di select_shipping.blade.php
    }
}