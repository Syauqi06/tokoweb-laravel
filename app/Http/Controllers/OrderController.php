<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Customer;
use App\Models\Produk;
use App\Models\Order;
use App\Models\OrderItem;
use Midtrans\Snap;
use Midtrans\Config;
use Illuminate\Support\Facades\Http;

class OrderController extends Controller
{
    public function statusProses()
    {
        $order = Order::whereIn('status', ['Paid', 'Kirim'])->orderBy('id', 'desc')->get();
        return view('backend.v_pesanan.proses', [
            'judul'    => 'Pesanan',
            'subJudul' => 'Pesanan Proses',
            'index'    => $order
        ]);
    }

    public function statusSelesai()
    {
        $order = Order::where('status', 'Selesai')->orderBy('id', 'desc')->get();
        return view('backend.v_pesanan.selesai', [
            'judul'    => 'Data Transaksi', 
            'subJudul' => 'Pesanan Selesai',
            'index'    => $order
        ]);
    }

    public function statusDetail($id)
    {
        $order = Order::findOrFail($id);
        return view('backend.v_pesanan.detail', [
            'judul'    => 'Data Transaksi', 
            'subJudul' => 'Detail Pesanan', 
            'order'    => $order,
        ]);
    }

    public function statusUpdate(Request $request, string $id)
    {
        $order = Order::findOrFail($id);

        $rules = [
            'alamat' => 'required',
        ];

        if ($request->status != $order->status) {
            $rules['status'] = 'required';
        }

        if ($request->noresi != $order->noresi) {
            $rules['noresi'] = 'required';
        }

        if ($request->pos != $order->pos) {
            $rules['pos'] = 'required';
        }

        $validatedData = $request->validate($rules);
        Order::where('id', $id)->update($validatedData);

        return redirect()->route('pesanan.proses')->with('success', 'Data berhasil diperbaharui');
    }

    public function addToCart($id)
    {
        $customer = Customer::where('user_id', Auth::id())->first();
        $produk = Produk::findOrFail($id);

        $order = Order::firstOrCreate(
            ['customer_id' => $customer->id, 'status' => 'pending'],
            ['total_harga' => 0]
        );

        $orderItem = OrderItem::firstOrCreate(
            ['order_id' => $order->id, 'produk_id' => $produk->id],
            ['quantity' => 1, 'harga' => $produk->harga]
        );

        if (!$orderItem->wasRecentlyCreated) {
            $orderItem->quantity++;
            $orderItem->save();
        }

        $order->total_harga += $produk->harga;
        $order->save();

        return redirect()->route('order.cart')->with('success', 'Produk berhasil ditambahkan ke keranjang');
    }

    public function viewCart()
    {
        $customer = Customer::where('user_id', Auth::id())->first();
        $order = Order::where('customer_id', $customer->id)->where('status', 'pending')->first();
        // Pastikan $order ada
        if (!$order) {
            return redirect()->route('order.cart')->with('error', 'Order tidak ditemukan.');
        }
        // Load relasi orderItems
        $order->load('orderItems.produk');

        return view('v_order.cart', compact('order'));
    }

    public function updateCart(Request $request, $id)
    {
        $customer = Customer::where('user_id', Auth::id())->first();;;
        $order = Order::where('customer_id', $customer->id)->where('status', 'pending')->first();
        if ($order) {
            $orderItem = $order->orderItems()->where('id', $id)->first();
            if ($orderItem) {
                $quantity = $request->input('quantity');
                if ($quantity > $orderItem->produk->stok) {
                    return redirect()->route('order.cart')->with('error', 'Jumlah produk melebihi stok yang tersedia');
                }
                $order->total_harga -= $orderItem->harga * $orderItem->quantity;
                $orderItem->quantity = $quantity;
                $orderItem->save();
                $order->total_harga += $orderItem->harga * $orderItem->quantity;
                $order->save();
            }
        }
        return redirect()->route('order.cart')->with('success', 'Jumlah produk berhasil diperbarui');
    }

    public function checkout()
    {
        $customer = Customer::where('user_id', Auth::id())->first();

        // Tambahkan pengecekan null
        if (!$customer) {
            return redirect()->route('order.cart')->with('error', 'Data customer tidak ditemukan');
        }

        $order = Order::where('customer_id', $customer->id)
            ->where('status', 'pending')
            ->first(); //

        // Pengecekan null sebelum diakses untuk orderItems
        if ($order) {
            foreach ($order->orderItems as $item) {
                $produk = $item->produk;
                if ($produk->stok >= $item->quantity) {
                    $produk->stok -= $item->quantity;
                    $produk->save();
                } else {
                    return redirect()->route('order.cart')->with('error', 'Stok produk ' . $produk->nama_produk . ' tidak mencukupi');
                }
            }

            $order->status = 'Paid'; //
            $order->save();
        }

        return redirect()->route('order.history')->with('success', 'Checkout berhasil');
    }

    public function orderHistory()
    {
        $customer = Customer::where('user_id', Auth::id())->first();

        // Tambahkan pengecekan null
        if (!$customer) {
            return redirect()->route('home')->with('error', 'Data customer tidak ditemukan');
        }

        $orders = Order::where('customer_id', $customer->id)
            ->whereIn('status', ['Paid', 'Kirim', 'Selesai'])
            ->orderBy('id', 'desc')
            ->get();

        return view('v_order.history', compact('orders')); // compact orders untuk mengirim data ke view
    }

    public function removeFromCart(Request $request, $id)
    {
        $customer = Customer::where('user_id', Auth::id())->first();
        $order = Order::where('customer_id', $customer->id)->where('status', 'pending')->first();

        if ($order) {
            $orderItem = OrderItem::where('order_id', $order->id)->where('produk_id', $id)->first();

            if ($orderItem) {
                $order->total_harga -= $orderItem->harga * $orderItem->quantity;
                $orderItem->delete();

                if ($order->total_harga <= 0) {
                    $order->delete();
                } else {
                    $order->save();
                }
            }
        }
        return redirect()->route('order.cart')->with('success', 'Produk berhasil dihapus dari keranjang');
    }

    public function selectShipping(Request $request)
    {
        $customer = Customer::where('user_id', Auth::id())->first();

        if (!$customer) {
            return redirect()->route('order.cart')->with('error', 'Data customer tidak ditemukan');
        }

        $order = Order::where('customer_id', $customer->id)
            ->where('status', 'pending')
            ->first();

        if (!$order || $order->orderItems->count() == 0) {
            return redirect()->route('order.cart')->with('error', 'Keranjang belanja kosong.');
        }

        return view('v_order.select_shipping', compact('order'));
    }

    public function updateOngkir(Request $request)
    {
        $customer = Customer::where('user_id', Auth::id())->first();

        if (!$customer) {
            return back()->with('error', 'Data customer tidak ditemukan');
        }

        $order = Order::where('customer_id', $customer->id)
            ->where('status', 'pending')
            ->first();

        if ($order) {
            $order->kurir          = $request->input('kurir');
            $order->layanan_ongkir = $request->input('layanan_ongkir');
            $order->biaya_ongkir   = $request->input('biaya_ongkir');
            $order->estimasi_ongkir = $request->input('estimasi_ongkir');
            $order->total_berat    = $request->input('total_berat');
            $order->alamat         = $request->input('alamat') . ', <br>' .
                                    $request->input('city_name') . ', <br>' .
                                    $request->input('province_name');
            $order->pos            = $request->input('pos');
            $order->save();

            return redirect()->route('order.select_payment');
        }

        return back()->with('error', 'Gagal menyimpan data ongkir');
    }

    public function selectPayment()
    {
        $customer = Customer::where('user_id', Auth::id())->first(); // ambil dari Customer

        if (!$customer) {
            return redirect()->route('home')->with('error', 'Data customer tidak ditemukan');
        }

        $order = Order::where('customer_id', $customer->id)
            ->where('status', 'pending')
            ->first();

        // Pengecekan null sebelum diakses
        if (!$order) {
            return redirect()->route('order.cart')->with('error', 'Order tidak ditemukan');
        }

        $order->load('orderItems.produk');

        $totalHarga = 0;
        foreach ($order->orderItems as $item) {
            $totalHarga += $item->harga * $item->quantity;
        }

        $grossAmount = $totalHarga + $order->biaya_ongkir;

        Config::$serverKey    = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = false;
        Config::$isSanitized  = true;
        Config::$is3ds        = true;

        $orderId = $order->id . '-' . time();

        $params = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => (int) $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $customer->nama,   // dari model Customer
                'email'      => Auth::user()->email,
                'phone'      => $customer->hp,     // dari model Customer
            ],
        ];

        $snapToken = Snap::getSnapToken($params);

        return view('v_order.select_payment', [
            'order'     => $order,
            'snapToken' => $snapToken,
        ]);
    }

    // Ongkir Provinsi
    public function getProvinces()
    {
        $response = Http::withHeaders([
            'key'    => env('RAJAONGKIR_API_KEY'),
            'Accept' => 'application/json',
        ])->get(env('RAJAONGKIR_BASE_URL') . '/province');

        // Ambil hanya bagian 'data' saja
        return response()->json($response->json()['data'] ?? []);
    }

    // Ongkir Kota
    public function getCities($provinceId)
    {
        $response = Http::withHeaders([
            'key'    => env('RAJAONGKIR_API_KEY'),
            'Accept' => 'application/json',
        ])->get(env('RAJAONGKIR_BASE_URL') . '/city/' . $provinceId);

        return response()->json($response->json()['data'] ?? []);
    }

    // Ongkir Biaya
    public function getCost(Request $request)
    {
        $response = Http::asForm()->withHeaders([
            'key'    => env('RAJAONGKIR_API_KEY'),
            'Accept' => 'application/json',
        ])->post(env('RAJAONGKIR_CALCULATE_URL') . '/domestic-cost', [
            'origin'      => $request->input('origin'),
            'destination' => $request->input('destination'),
            'weight'      => (int) $request->input('weight'),
            'courier'     => $request->input('courier'),
        ]);

        return response()->json($response->json()['data'] ?? []);
    }

    // public function selectPayment()
    // {
    //     $customer = Auth::user();
    //     $order = Order::where('customer_id', $customer->customer->id)->where('status', 'pending')->first();

    //     $origin = session('origin');        // Kode kota asal
    //     $originName = session('originName'); // Nama kota asal


    //     if (!$order) {
    //         return redirect()->route('order.cart')->with('error', 'Keranjang belanja kosong.');
    //     }

    //     // Muat relasi orderItems dan produk terkait
    //     $order->load('orderItems.produk');

    //     // Hitung total harga produk
    //     $totalHarga = 0;
    //     foreach ($order->orderItems as $item) {
    //         $totalHarga += $item->harga * $item->quantity;
    //     }

    //     // Tambahkan biaya ongkir ke total harga
    //     $grossAmount = $totalHarga + $order->biaya_ongkir;

    //     // Midtrans configuration
    //     Config::$serverKey = config('midtrans.server_key');
    //     Config::$isProduction = false;
    //     Config::$isSanitized = true;
    //     Config::$is3ds = true;

    //     // Generate unique order_id
    //     $orderId = $order->id . '-' . time();

    //     $params = [
    //         'transaction_details' => [
    //             'order_id' => $orderId,
    //             'gross_amount' => (int) $grossAmount, // Pastikan gross_amount adalah integer
    //         ],
    //         'customer_details' => [
    //             'first_name' => $customer->nama,
    //             'email' => $customer->email,
    //             'phone' => $customer->hp,
    //         ],
    //     ];

    //     $snapToken = Snap::getSnapToken($params);
    //     return view('v_order.select_payment', [
    //         'order' => $order,
    //         'origin' => $origin,
    //         'originName' => $originName,
    //         'snapToken' => $snapToken,
    //     ]);
    // }

    public function callback(Request $request)
    {
        // dd($request->all());
        $serverKey = config('midtrans.server_key');
        $hashed = hash("sha512", $request->order_id . $request->status_code . $request->gross_amount . $serverKey);
        if ($hashed == $request->signature_key) {
            $order = Order::find($request->order_id);
            if ($order) {
                $order->update(['status' => 'Paid']);
            }
        }
    }

    public function complete() // saat callback, langsung update status ke Paid
    {
        // Dapatkan customer yang login
        $customer = Auth::user();

        // Cari order dengan status 'pending' milik customer tersebut
        $order = Order::where('customer_id', $customer->customer->id)
            ->where('status', 'pending')
            ->first();

        if ($order) {
            $order->status = 'Paid';
            $order->save();
        }

        // Redirect ke halaman riwayat dengan pesan sukses
        return redirect()->route('order.history')->with('success', 'Checkout berhasil');
    }


    // public function complete() // Untuk kondisi sudah memiliki domain
    // {
    //     // Logika untuk halaman setelah pembayaran berhasil
    //     return redirect()->route('order.history')->with('success', 'Checkout berhasil');
    // }

    // public function orderHistory()
    // {
    //     $customer = Customer::where('user_id', Auth::id())->first();;;
    //     // $orders = Order::where('customer_id', $customer->id)->where('status', 'completed')->get();
    //     $statuses = ['Paid', 'Kirim', 'Selesai'];
    //     $orders = Order::where('customer_id', $customer->id)
    //         ->whereIn('status', $statuses)
    //         ->orderBy('id', 'desc')
    //         ->get();
    //     return view('v_order.history', compact('orders'));
    // }

    public function invoiceFrontend($id)
    {
        $order = Order::findOrFail($id);
        return view('v_order.invoice', [
            'judul' => 'Pesanan',
            'subJudul' => 'Pesanan Proses',
            'order' => $order,
        ]);
    }
}
