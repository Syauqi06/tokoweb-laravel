@extends('backend.v_layouts.app')
@section('content')

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body border-top">
                <h5 class="card-title">{{ $judul }}</h5>
                <div class="alert alert-success" role="alert">
                    <h4 class="alert-heading">Selamat Datang, {{ Auth::user()->nama }}</h4>
                    Aplikasi Toko Online dengan hak akses yang anda miliki sebagai
                    <b>
                        @if (Auth::user()->role == 1)
                            Super Admin
                        @elseif(Auth::user()->role == 0)
                            Admin
                        @endif
                    </b>
                    ini adalah halaman utama dari aplikasi Web Programming. Studi Kasus Toko Online.
                    <hr>
                    <p class="mb-0">Kuliah..? BSI Aja !!!</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Site Analysis -->
<div class="row">
    <!-- Chart -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Site Analysis</h4>
                <h6 class="card-subtitle">Overview of Latest Month</h6>
                <div class="chart-wrapper" style="height: 300px; margin-top: 20px;">
                    <canvas id="siteAnalysisChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="col-lg-4">
        <div class="row">
            <div class="col-6">
                <div class="card" style="background:#2b2d3e; color:white; text-align:center; padding:20px;">
                    <i class="fa fa-users fa-2x mb-2"></i>
                    <h3>{{ $totalUsers }}</h3>
                    <span>Total Users</span>
                </div>
            </div>
            <div class="col-6">
                <div class="card" style="background:#2b2d3e; color:white; text-align:center; padding:20px;">
                    <i class="fa fa-plus fa-2x mb-2"></i>
                    <h3>{{ $newUsers }}</h3>
                    <span>New Users</span>
                </div>
            </div>
            <div class="col-6">
                <div class="card" style="background:#2b2d3e; color:white; text-align:center; padding:20px;">
                    <i class="fa fa-shopping-cart fa-2x mb-2"></i>
                    <h3>{{ $totalShop }}</h3>
                    <span>Total Shop</span>
                </div>
            </div>
            <div class="col-6">
                <div class="card" style="background:#2b2d3e; color:white; text-align:center; padding:20px;">
                    <i class="fa fa-tag fa-2x mb-2"></i>
                    <h3>{{ $totalOrders }}</h3>
                    <span>Total Orders</span>
                </div>
            </div>
            <div class="col-6">
                <div class="card" style="background:#2b2d3e; color:white; text-align:center; padding:20px;">
                    <i class="fa fa-clock-o fa-2x mb-2"></i>
                    <h3>{{ $pendingOrders }}</h3>
                    <span>Pending Orders</span>
                </div>
            </div>
            <div class="col-6">
                <div class="card" style="background:#2b2d3e; color:white; text-align:center; padding:20px;">
                    <i class="fa fa-globe fa-2x mb-2"></i>
                    <h3>{{ $onlineOrders }}</h3>
                    <span>Online Orders</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('siteAnalysisChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
            datasets: [
                {
                    label: 'sin(x)',
                    data: [0, 0.84, 0.91, 0.14, -0.76, -0.96, -0.28, 0.66, 0.99, 0.41, -0.54],
                    borderColor: '#f96868',
                    backgroundColor: 'transparent',
                    pointStyle: 'rectRot',
                    tension: 0.4
                },
                {
                    label: 'cos(x)',
                    data: [1, 0.54, -0.41, -0.99, -0.65, 0.28, 0.96, 0.75, -0.15, -0.91, -0.84],
                    borderColor: '#46c6f1',
                    backgroundColor: 'transparent',
                    pointStyle: 'rectRot',
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: { min: -1.0, max: 1.0 }
            }
        }
    });
</script>

@endsection