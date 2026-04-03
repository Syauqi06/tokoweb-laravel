<!DOCTYPE html>
<html>
<head>
    <title>Cek Ongkir</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
    <form id="ongkirForm">
        <select name="province" id="province">
            <option value="">Pilih Provinsi</option>
        </select>

        <select name="city" id="city">
            <option value="">Pilih Kota</option>
        </select>

        <input type="number" name="weight" id="weight" placeholder="Berat (gram)">

        <select name="courier" id="courier">
            <option value="">Pilih Kurir</option>
            <option value="jne">JNE</option>
            <option value="tiki">TIKI</option>
            <option value="pos">POS Indonesia</option>
        </select>

        <button type="submit">Cek Ongkir</button>
    </form>

    <div id="result" style="margin-top: 20px;"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Fetch Provinsi
            fetch('/provinces')
                .then(response => response.json())
                .then(data => {
                    // Komerce: response langsung array (sudah difilter di controller)
                    let provinceSelect = document.getElementById('province');
                    data.forEach(province => {
                        let option = document.createElement('option');
                        option.value = province.id;           // bukan province_id
                        option.textContent = province.name;   // bukan province
                        provinceSelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Error fetching provinces:', error));

            // 2. Fetch Kota Berdasarkan Provinsi
            document.getElementById('province').addEventListener('change', function() {
                let provinceId = this.value;
                let citySelect = document.getElementById('city');

                citySelect.innerHTML = '<option value="">Pilih Kota</option>';

                if (provinceId) {
                    fetch(`/cities/${provinceId}`)   // bukan /cities?province_id=
                        .then(response => response.json())
                        .then(data => {
                            data.forEach(city => {
                                let option = document.createElement('option');
                                option.value = city.id;
                                option.textContent = city.name;
                                citySelect.appendChild(option);
                            });
                        })
                        .catch(error => console.error('Error fetching cities:', error));
                }
            });
            

            // 3. Submit Cek Ongkir
            document.getElementById('ongkirForm').addEventListener('submit', function(event) {
                event.preventDefault();

                let origin      = 501;
                let destination = document.getElementById('city').value;
                let weight      = document.getElementById('weight').value;
                let courier     = document.getElementById('courier').value;

                if (!destination || !weight || !courier) {
                    alert('Mohon lengkapi semua data');
                    return;
                }

                let resultDiv = document.getElementById('result');
                resultDiv.innerHTML = 'Sedang memuat...';

                fetch('/cost', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        origin: origin,
                        destination: destination,
                        weight: parseInt(weight),
                        courier: courier
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (!data || data.length === 0) {
                        resultDiv.innerHTML = 'Layanan tidak tersedia.';
                        return;
                    }

                    resultDiv.innerHTML = `<h3>Hasil Ongkir (${courier.toUpperCase()}):</h3>`;
                    data.forEach(item => {
                        let div = document.createElement('div');
                        div.style.padding = '10px';
                        div.style.borderBottom = '1px solid #ccc';
                        div.innerHTML = `
                            <strong>${item.service}</strong><br>
                            Harga: Rp ${item.cost.toLocaleString()}<br>
                            Estimasi: ${item.etd}
                        `;
                        resultDiv.appendChild(div);
                    });
                })
                .catch(error => {
                    console.error('Error fetching cost:', error);
                    resultDiv.innerHTML = 'Terjadi kesalahan sistem.';
                });
            });
        });
    </script>
</body>
</html>