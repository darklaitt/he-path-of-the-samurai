@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 1400px; margin: 0 auto;">
  <div class="mb-4">
    <h3>Астрономические События</h3>
  </div>
  
  <div class="row mb-4">
    <div class="col-md-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex gap-2 align-items-center flex-wrap">
            <input type="number" id="lat" class="form-control" placeholder="Широта" value="55.7558" step="0.0001" style="max-width: 150px;">
            <input type="number" id="lon" class="form-control" placeholder="Долгота" value="37.6176" step="0.0001" style="max-width: 150px;">
            <input type="date" id="from_date" class="form-control" style="max-width: 180px;">
            <input type="date" id="to_date" class="form-control" style="max-width: 180px;">
            <input type="text" id="search" class="form-control" placeholder="Поиск..." style="max-width: 200px;">
            <button id="loadBtn" class="btn btn-primary">Загрузить</button>
          </div>
          <div class="mt-2">
            <span id="status" class="text-muted"></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-md-8">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title">События</h5>
          <div id="eventsTable">
            <div class="text-center text-muted py-4">Загрузка данных...</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <h5 class="card-title">Фазы Луны</h5>
          <div id="moonPhases">
            <div class="text-muted">—</div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title">События Солнца</h5>
          <div id="sunEvents">
            <div class="text-muted">—</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.event-row {
  padding: 10px;
  border-bottom: 1px solid #ecf0f1;
}
.event-row:last-child {
  border-bottom: none;
}
.event-date {
  font-weight: 600;
  color: #2c3e50;
}
.event-type {
  color: #3498db;
  font-size: 14px;
}
.event-desc {
  color: #7f8c8d;
  font-size: 13px;
}
.phase-item, .sun-item {
  padding: 8px 0;
  border-bottom: 1px solid #ecf0f1;
}
.phase-item:last-child, .sun-item:last-child {
  border-bottom: none;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const eventsTable = document.getElementById('eventsTable');
  const moonPhases = document.getElementById('moonPhases');
  const sunEvents = document.getElementById('sunEvents');
  const status = document.getElementById('status');
  const loadBtn = document.getElementById('loadBtn');

  // Установка дат по умолчанию (1 год для лучшего охвата событий)
  const today = new Date();
  const nextYear = new Date(today);
  nextYear.setFullYear(today.getFullYear() + 1);
  
  document.getElementById('from_date').valueAsDate = today;
  document.getElementById('to_date').valueAsDate = nextYear;

  async function loadEvents() {
    status.textContent = 'Загрузка...';
    eventsTable.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';

    const params = new URLSearchParams({
      lat: document.getElementById('lat').value,
      lon: document.getElementById('lon').value,
      from_date: document.getElementById('from_date').value,
      to_date: document.getElementById('to_date').value,
      search: document.getElementById('search').value
    });

    try {
      const response = await fetch(`/api/astro/events?${params}`);
      const data = await response.json();
      
      if (!data.ok) {
        // Улучшенное отображение ошибки 403
        if (data.error === 'HTTP 403') {
          status.textContent = 'Ошибка: Превышена квота API или требуется обновление ключа';
          eventsTable.innerHTML = '<div class="alert alert-warning">API AstronomyAPI недоступен (403 Forbidden). Проверьте настройки ASTRO_APP_ID и ASTRO_APP_SECRET в docker-compose.yml</div>';
        } else {
          status.textContent = 'Ошибка: ' + (data.error || 'неизвестная');
          eventsTable.innerHTML = '<div class="text-danger">Не удалось загрузить данные</div>';
        }
        return;
      }

      // Отображение событий
      if (data.data.length === 0) {
        eventsTable.innerHTML = '<div class="text-muted">События не найдены</div>';
      } else {
        let html = '';
        data.data.forEach(event => {
          html += `
            <div class="event-row">
              <div class="event-date">${event.date} ${event.time}</div>
              <div class="event-type">${event.type}</div>
              <div class="event-desc">${event.description || '—'}</div>
            </div>
          `;
        });
        eventsTable.innerHTML = html;
      }

      // Отображение фаз Луны
      if (data.moon && data.moon.length > 0) {
        let moonHtml = '';
        data.moon.forEach(phase => {
          moonHtml += `
            <div class="phase-item">
              <strong>${phase.phase}</strong><br>
              <small class="text-muted">${phase.date}${phase.time ? ' ' + phase.time : ''}</small>
            </div>
          `;
        });
        moonPhases.innerHTML = moonHtml;
      } else {
        moonPhases.innerHTML = '<div class="text-muted">Нет данных</div>';
      }

      // Отображение событий Солнца
      if (data.sun && data.sun.length > 0) {
        let sunHtml = '';
        data.sun.forEach(event => {
          sunHtml += `
            <div class="sun-item">
              <strong>${event.type}</strong><br>
              <small class="text-muted">${event.date ? event.date + ' ' : ''}${event.time}</small>
            </div>
          `;
        });
        sunEvents.innerHTML = sunHtml;
      } else {
        sunEvents.innerHTML = '<div class="text-muted">Нет данных</div>';
      }

    } catch (error) {
      status.textContent = 'Ошибка: ' + error.message;
      eventsTable.innerHTML = '<div class="text-danger">Ошибка сети</div>';
      console.error('Astronomy API error:', error);
    }
  }

  loadBtn.addEventListener('click', loadEvents);
  
  // Автозагрузка при открытии
  loadEvents();
});
</script>
@endsection
