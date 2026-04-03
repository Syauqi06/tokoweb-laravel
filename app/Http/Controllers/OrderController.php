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

class OrderController extends Controller
{
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
        // Mendapatkan customer berdasarkan user yang login
        $customer = Customer::where('user_id', Auth::id())->first();

        // Pastikan order dengan status 'pending' ada untuk customer ini
        $order = Order::where('customer_id', $customer->id)->where('status', 'pending')->first();

        // Cek apakah order ada
        if (!$order) {
            return redirect()->route('order.cart')->with('error', 'Keranjang belanja kosong.');
        }

        // Pastikan orderItems sudah dimuat menggunakan eager loading
        $order->load('orderItems.produk');

        // Lanjutkan ke view jika order ada
        return view('v_order.select_shipping', compact('order'));
    }

    public function updateOngkir(Request $request)
    {
        $customer = Customer::where('user_id', Auth::id())->first();
        $order = Order::where('customer_id', $customer->id)->where('status', 'pending')->first();

        $origin = $request->input('city_origin'); // kode kota asal
        $originName = $request->input('city_origin_name'); // nama kota asal

        if ($order) {
            // Simpan data ongkir ke dalam order
            $order->kurir = $request->input('kurir');
            $order->layanan_ongkir = $request->input('layanan_ongkir');
            $order->biaya_ongkir = $request->input('biaya_ongkir');
            $order->estimasi_ongkir = $request->input('estimasi_ongkir');
            $order->total_berat = $request->input('total_berat');
            $order->alamat = $request->input('alamat') . ', <br>' . $request->input('city_name') . ', <br>' . $request->input('province_name');
            $order->pos = $request->input('pos');
            $order->save();
            // Simpan ke session flash agar bisa diakses di halaman tujuan
            return redirect()->route('order.selectpayment')
                ->with('origin', $origin)
                ->with('originName', $originName);
        }

        return back()->with('error', 'Gagal menyimpan data ongkir');
    }

    public function selectPayment()
    {
        $customer = Auth::user();
        $order = Order::where('customer_id', $customer->customer->id)->where('status', 'pending')->first();

        $origin = session('origin');        // Kode kota asal
        $originName = session('originName'); // Nama kota asal


        if (!$order) {
            return redirect()->route('order.cart')->with('error', 'Keranjang belanja kosong.');
        }

        // Muat relasi orderItems dan produk terkait
        $order->load('orderItems.produk');

        // Hitung total harga produk
        $totalHarga = 0;
        foreach ($order->orderItems as $item) {
            $totalHarga += $item->harga * $item->quantity;
        }

        // Tambahkan biaya ongkir ke total harga
        $grossAmount = $totalHarga + $order->biaya_ongkir;

        // Midtrans configuration
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = false;
        Config::$isSanitized = true;
        Config::$is3ds = true;

        // Generate unique order_id
        $orderId = $order->id . '-' . time();

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $grossAmount, // Pastikan gross_amount adalah integer
            ],
            'customer_details' => [
                'first_name' => $customer->nama,
                'email' => $customer->email,
                'phone' => $customer->hp,
            ],
        ];

        $snapToken = Snap::getSnapToken($params);
        return view('v_order.select_payment', [
            'order' => $order,
            'origin' => $origin,
            'originName' => $originName,
            'snapToken' => $snapToken,
        ]);
    }

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

    public function complete() // Untuk kondisi local
    {
        // Dapatkan customer yang login
        $customer = Auth::user();

        // Cari order dengan status 'pending' milik customer tersebut
        $order = Order::where('customer_id', $customer->customer->id)
            ->where('status', 'pending')
            ->first();

        if ($order) {
            // Update status order menjadi 'Paid'
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

    public function orderHistory()
    {
        $customer = Customer::where('user_id', Auth::id())->first();;;
        // $orders = Order::where('customer_id', $customer->id)->where('status', 'completed')->get();
        $statuses = ['Paid', 'Kirim', 'Selesai'];
        $orders = Order::where('customer_id', $customer->id)
            ->whereIn('status', $statuses)
            ->orderBy('id', 'desc')
            ->get();
        return view('v_order.history', compact('orders'));
    }

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
