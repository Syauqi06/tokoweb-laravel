@extends('v_layouts.app')
@section('content')
<!-- template -->

<div class="col-md-12" hidden>
    <div class="order-summary clearfix">
        <div class="section-title">
            <p>PENGIRIMAN</p>
            <h3 class="title">Produk</h3>
        </div>
        @if($order && $order->orderItems->count() > 0)
        <table class="shopping-cart-table table">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th></th>
                    <th class="text-center">Harga</th>
                    <th class="text-center">Quantity</th>
                    <th class="text-center">Total</th>
                </tr>
            </thead>
            <tbody>
                @php
                $totalHarga = 0;
                $totalBerat = 0;
                @endphp
                @foreach($order->orderItems as $item)
                @php
                $totalHarga += $item->harga * $item->quantity;
                $totalBerat += $item->produk->berat * $item->quantity;
                @endphp
                <tr>
                    <td class="thumb"><img src="{{ asset('storage/img-produk/thumb_sm_' . $item->produk->foto) }}" alt=""></td>
                    <td class="details">
                        <a>{{ $item->produk->nama_produk }}</a>
                        <ul>
                            <li><span>Berat: {{ $item->produk->berat }} Gram</span></li>
                        </ul>
                        <ul>
                            <li><span>Stok: {{ $item->produk->stok }} Gram</span></li>
                        </ul>
                    </td>
                    <td class="price text-center"><strong>Rp. {{ number_format($item->harga, 0, ',', '.') }}</strong></td>
                    <td class="qty text-center">
                        <a> {{ $item->quantity }} </a>
                    </td>
                    <td class="total text-center"><strong class="primary-color">Rp. {{ number_format($item->harga * $item->quantity, 0, ',', '.') }}</strong></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p>Keranjang belanja kosong.</p>
        @endif
    </div>
</div>

<div class="col-md-12">
    <div class="order-summary clearfix">
        <div class="section-title">
            <p>PENGIRIMAN</p>
            <h3 class="title">Pilih Pengiriman</h3>
        </div>
        <form id="shippingForm">
            <!-- Kota Asal -->
            <input type="hidden" id="city_origin" name="city_origin" value="">
            <input type="hidden" id="city_origin_name" name="city_origin_name" value="">
            <!-- /Kota Asal -->

            <div class="form-group">
                <label for="province">Provinsi Tujuan:</label>
                <select name="province" id="province" class="input">
                    <option value="">Pilih Provinsi</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="city">Kota Tujuan:</label>
                <select name="cities" id="cities" class="input">
                    <option value="">Pilih Kota</option>
                </select>
            </div>
            
            <input type="hidden" name="weight" id="weight" value="{{ $totalBerat }}">
            <input type="hidden" name="province_name" id="province_name">
            <input type="hidden" name="city_name" id="city_name">
            <div class="form-group">
                <label for="courier">Kurir:</label>
                <select name="courier" id="courier" class="input">
                    <option value="">Pilih Kurir</option>
                    <option value="jne">JNE</option>
                    <option value="tiki">TIKI</option>
                    <option value="pos">POS Indonesia</option>
                </select>
            </div>
            <div class="form-group">
                <label for="">Alamat</label>
                <textarea class="input" name="alamat" id="alamat">{{ Auth::user()->alamat }}</textarea>
            </div>
            <div class="form-group">
                <label for="">Kode Pos</label>
                <input type="text" class="input" name="kode_pos" id="kode_pos" value="{{ Auth::user()->pos }}">
            </div>
            <button type="submit" class="primary-btn">Cek Ongkir</button>
        </form>

        <br>
        <div id="result">
            <table class="shopping-cart-table table">
                <thead>
                    <tr>
                        <th>Layanan</th>
                        <th>Biaya</th>
                        <th>Estimasi Pengiriman</th>
                        <th>Total Berat</th>
                        <th>Total Harga</th>
                        <th>Bayar</th>
                    </tr>
                </thead>
                <tbody id="shippingResults">
                    <!-- Hasil dari pencarian akan dimuat di sini -->
                </tbody>
            </table>
        </div>
    </div>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function () {

        // Fetch Provinsi saat halaman dimuat
        fetch('/provinces')
            .then(res => res.json()) // Mengambil data provinsi dari endpoint /provinces
            .then(data => {
                let provinceSelect = document.getElementById('province'); // Mendapatkan elemen select untuk provinsi
                data.forEach(province => {
                    let option = document.createElement('option');
                    option.value = province.id;
                    option.textContent = province.name;
                    provinceSelect.appendChild(option);
                });
            })
            .catch(error => console.error('Error fetching provinces:', error));

        // Fetch Kota saat Provinsi dipilih
        document.getElementById('province').addEventListener('change', function () {
            const provinceId = this.value;
            const citySelect = document.getElementById('cities');
            citySelect.innerHTML = '<option value="">Pilih Kota</option>';

            // Simpan nama provinsi ke hidden input
            let selectedOption = this.options[this.selectedIndex];
            document.getElementById('province_name').value = selectedOption.text; // Simpan nama kota ke hidden input (reset saat provinsi berubah)

            if (!provinceId) return; // Jika tidak ada provinsi yang dipilih, hentikan eksekusi

            fetch(`/cities/${provinceId}`) // Mengambil data kota berdasarkan provinsi yang dipilih
                .then(res => res.json())
                .then(data => {
                    data.forEach(city => {
                        let option = document.createElement('option');
                        option.value = city.id;
                        option.textContent = city.name;
                        citySelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Error fetching cities:', error));
        });

        // Simpan nama kota ke hidden input saat kota dipilih
        document.getElementById('cities').addEventListener('change', function () {
            let selectedOption = this.options[this.selectedIndex];
            document.getElementById('city_name').value = selectedOption.text;
        });

        // Handle form submission
        document.getElementById('shippingForm').addEventListener('submit', function (event) {
            event.preventDefault();

            let origin      = 63;
            let destination = document.getElementById('cities').value;
            let weight      = document.getElementById('weight').value;
            let courier     = document.getElementById('courier').value;
            let alamat      = document.getElementById('alamat').value;
            let kodePos     = document.getElementById('kode_pos').value;

            if (!alamat.trim() || !kodePos.trim()) {
                alert('Harap lengkapi alamat dan kode pos.');
                return;
            }

            if (!destination || !weight || !courier) {
                alert('Harap lengkapi semua kolom.');
                return;
            }

            fetch('/cost', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', // Menentukan tipe konten sebagai JSON
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') 
                },
                body: JSON.stringify({
                    origin:      origin,
                    destination: destination,
                    weight:      parseInt(weight), // Parseint untuk memastikan weight dikirim sebagai angka
                    courier:     courier
                })
            })
            .then(response => response.json())
            .then(data => {
                // console.log('Cost response:', data); //
                // console.log('Origin:', origin);
                // console.log('Destination:', destination);
                // console.log('Weight:', weight);
                // console.log('Courier:', courier);
                let shippingResults = document.getElementById('shippingResults');
                shippingResults.innerHTML = '';

                if (!data || data.length === 0) {
                    shippingResults.innerHTML = '<tr><td colspan="6">Layanan tidak tersedia.</td></tr>';
                    return;
                }

                data.forEach(item => {
                    let row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${item.service}</td>
                        <td>Rp ${item.cost.toLocaleString()}</td>
                        <td>${item.etd}</td>
                        <td>${weight} Gram</td>
                        <td>Rp. {{ number_format($totalHarga, 0, ',', '.') }}</td>
                        <td>
                            <form action="{{ route('order.update-ongkir') }}" method="post">
                                @csrf
                                <input type="hidden" name="province" value="${document.getElementById('province').value}">
                                <input type="hidden" name="city" value="${document.getElementById('cities').value}">
                                <input type="hidden" name="province_name" value="${document.getElementById('province_name').value}">
                                <input type="hidden" name="city_name" value="${document.getElementById('city_name').value}">
                                <input type="hidden" name="kurir" value="${courier}">
                                <input type="hidden" name="alamat" value="${alamat}">
                                <input type="hidden" name="pos" value="${kodePos}">
                                <input type="hidden" name="layanan_ongkir" value="${item.service}">
                                <input type="hidden" name="biaya_ongkir" value="${item.cost}">
                                <input type="hidden" name="estimasi_ongkir" value="${item.etd}">
                                <input type="hidden" name="total_berat" value="${weight}">
                                <input type="hidden" name="city_origin" value="${origin}">
                                <button type="submit" class="primary-btn">Pilih Pengiriman</button>
                            </form>
                        </td>
                    `;
                    shippingResults.appendChild(row);
                });
            })
            .catch(error => console.error('Error fetching cost:', error));
        });

    });
</script>

<!-- end template-->
@endsection