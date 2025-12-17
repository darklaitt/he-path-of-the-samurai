@extends('layouts.app')

@section('content')
<div class="container pb-5" style="max-width: 1400px; margin: 0 auto;">
  <h3 class="mb-3">Международная космическая станция (МКС)</h3>
  
  {{-- Верхние карточки с основными параметрами --}}
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
      <div class="card shadow-sm text-center">
        <div class="card-body">
          <div class="small text-muted">Широта</div>
          <div class="fs-4 text-primary">{{ number_format($last['payload']['latitude'] ?? 0, 4) }}°</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm text-center">
        <div class="card-body">
          <div class="small text-muted">Долгота</div>
          <div class="fs-4 text-primary">{{ number_format($last['payload']['longitude'] ?? 0, 4) }}°</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm text-center">
        <div class="card-body">
          <div class="small text-muted">Скорость</div>
          <div class="fs-4 text-success">{{ number_format($last['payload']['velocity'] ?? 0, 0, '', ' ') }} км/ч</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card shadow-sm text-center">
        <div class="card-body">
          <div class="small text-muted">Высота</div>
          <div class="fs-4 text-info">{{ number_format($last['payload']['altitude'] ?? 0, 2) }} км</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    {{-- Карта и графики --}}
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title">Положение и движение</h5>
          <div id="map" class="rounded mb-3 border" style="height:400px"></div>
          <div class="row g-2">
            <div class="col-md-6">
              <canvas id="issSpeedChart" height="150"></canvas>
            </div>
            <div class="col-md-6">
              <canvas id="issAltChart" height="150"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Детальная информация --}}
    <div class="col-lg-4">
      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <h5 class="card-title">Текущие данные</h5>
          @if(!empty($last['payload']))
            <table class="table table-sm">
              <tr><td class="text-muted">Широта</td><td>{{ $last['payload']['latitude'] ?? '—' }}°</td></tr>
              <tr><td class="text-muted">Долгота</td><td>{{ $last['payload']['longitude'] ?? '—' }}°</td></tr>
              <tr><td class="text-muted">Высота</td><td>{{ number_format($last['payload']['altitude'] ?? 0, 2) }} км</td></tr>
              <tr><td class="text-muted">Скорость</td><td>{{ number_format($last['payload']['velocity'] ?? 0, 2) }} км/ч</td></tr>
              <tr><td class="text-muted">Видимость</td><td>{{ $last['payload']['visibility'] ?? '—' }}</td></tr>
              <tr><td class="text-muted">Обновлено</td><td><small>{{ $last['fetched_at'] ?? '—' }}</small></td></tr>
            </table>
          @else
            <div class="text-muted">Нет данных</div>
          @endif
          <small class="text-muted"><code>{{ $base }}/last</code></small>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title">Тренд движения</h5>
          @if(!empty($trend))
            <table class="table table-sm">
              <tr><td class="text-muted">Движение</td><td><span class="badge bg-{{ ($trend['movement'] ?? false) ? 'success' : 'secondary' }}">{{ ($trend['movement'] ?? false) ? 'Да' : 'Нет' }}</span></td></tr>
              <tr><td class="text-muted">Смещение</td><td>{{ number_format($trend['delta_km'] ?? 0, 3) }} км</td></tr>
              <tr><td class="text-muted">Интервал</td><td>{{ number_format($trend['dt_sec'] ?? 0, 1) }} сек</td></tr>
              <tr><td class="text-muted">Скорость</td><td>{{ number_format($trend['velocity_kmh'] ?? 0, 2) }} км/ч</td></tr>
            </table>
          @else
            <div class="text-muted">Нет данных</div>
          @endif
          <small class="text-muted"><code>{{ $base }}/iss/trend</code></small>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
  if (typeof L === 'undefined' || typeof Chart === 'undefined') {
    console.error('Leaflet или Chart.js не загружены');
    return;
  }

  // Инициализация карты
  const last = @json(($last['payload'] ?? []));
  let lat0 = Number(last.latitude || 0);
  let lon0 = Number(last.longitude || 0);
  
  const map = L.map('map', { 
    attributionControl: false,
    zoomControl: true
  }).setView([lat0 || 0, lon0 || 0], lat0 ? 3 : 2);
  
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    noWrap: true
  }).addTo(map);

  const trail = L.polyline([], {
    color: '#3498db',
    weight: 3,
    opacity: 0.7
  }).addTo(map);
  
  const marker = L.marker([lat0 || 0, lon0 || 0], {
    icon: L.icon({
      iconUrl: 'https://cdn-icons-png.flaticon.com/512/1684/1684381.png',
      iconSize: [32, 32],
      iconAnchor: [16, 16]
    })
  }).addTo(map).bindPopup('МКС');

  // График скорости
  const speedChart = new Chart(document.getElementById('issSpeedChart'), {
    type: 'line',
    data: {
      labels: [],
      datasets: [{
        label: 'Скорость (км/ч)',
        data: [],
        borderColor: '#2ecc71',
        backgroundColor: 'rgba(46, 204, 113, 0.1)',
        tension: 0.4,
        fill: true
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: { display: true, position: 'top' }
      },
      scales: {
        x: { display: false },
        y: { beginAtZero: false }
      }
    }
  });

  // График высоты
  const altChart = new Chart(document.getElementById('issAltChart'), {
    type: 'line',
    data: {
      labels: [],
      datasets: [{
        label: 'Высота (км)',
        data: [],
        borderColor: '#3498db',
        backgroundColor: 'rgba(52, 152, 219, 0.1)',
        tension: 0.4,
        fill: true
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: { display: true, position: 'top' }
      },
      scales: {
        x: { display: false },
        y: { beginAtZero: false }
      }
    }
  });

  // Загрузка и обновление данных
  async function loadTrend() {
    try {
      const response = await fetch('/api/iss/trend?limit=240');
      const data = await response.json();
      
      if (data.points && Array.isArray(data.points)) {
        const points = data.points.map(p => [p.lat, p.lon]);
        const times = data.points.map(p => new Date(p.at).toLocaleTimeString());
        const speeds = data.points.map(p => p.velocity);
        const altitudes = data.points.map(p => p.altitude);

        if (points.length > 0) {
          trail.setLatLngs(points);
          marker.setLatLng(points[points.length - 1]);
          map.setView(points[points.length - 1], 3);
        }

        speedChart.data.labels = times;
        speedChart.data.datasets[0].data = speeds;
        speedChart.update();

        altChart.data.labels = times;
        altChart.data.datasets[0].data = altitudes;
        altChart.update();
      }
    } catch (error) {
      console.error('Ошибка загрузки данных МКС:', error);
    }
  }

  // Первая загрузка и автообновление каждые 15 секунд
  loadTrend();
  setInterval(loadTrend, 15000);
});
</script>
@endsection
