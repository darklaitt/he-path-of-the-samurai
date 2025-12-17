@extends('layouts.app')

@section('content')
<div class="container py-3 fade-in" style="max-width: 1400px; margin: 0 auto;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">NASA OSDR</h3>
      <div class="small text-muted">Источник: {{ $src ?? 'Service: OsdrService' }}</div>
    </div>
    <form id="osdrFilters" class="row g-2 align-items-center">
      <div class="col-auto">
        <label class="form-label small mb-0">Поиск</label>
        <input type="text" class="form-control form-control-sm" name="q" placeholder="dataset_id / title / keyword" value="{{ $search_query ?? '' }}">
      </div>
      <div class="col-auto">
        <label class="form-label small mb-0">Сортировка</label>
        <select class="form-select form-select-sm" name="sort">
          <option value="inserted_desc" @selected(($sort ?? '') === 'inserted_desc')>Дата (новые → старые)</option>
          <option value="inserted_asc"  @selected(($sort ?? '') === 'inserted_asc')>Дата (старые → новые)</option>
          <option value="title_asc"     @selected(($sort ?? '') === 'title_asc')>Заголовок A→Z</option>
          <option value="title_desc"    @selected(($sort ?? '') === 'title_desc')>Заголовок Z→A</option>
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label small mb-0">Кол-во</label>
        <select class="form-select form-select-sm" name="limit">
          @foreach([10,20,50,100] as $l)
            <option value="{{ $l }}" @selected(($limit ?? 20) == $l)>{{ $l }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label small mb-0">&nbsp;</label>
        <div>
          <button class="btn btn-sm btn-primary" type="submit">Применить</button>
        </div>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="card-title mb-0">Datasets</h5>
        <div class="small text-muted">
          Всего записей: {{ count($items ?? []) }}
        </div>
      </div>

  <div class="table-responsive">
        <table class="table table-sm table-hover align-middle" id="osdrTable">
      <thead>
        <tr>
              <th data-col="id" class="sortable">#</th>
              <th data-col="dataset_id" class="sortable">dataset_id</th>
              <th data-col="title" class="sortable">title</th>
          <th>REST_URL</th>
              <th data-col="updated_at" class="sortable">updated_at</th>
              <th data-col="inserted_at" class="sortable">inserted_at</th>
          <th>raw</th>
        </tr>
      </thead>
      <tbody>
      @forelse($items as $row)
            <tr data-id="{{ $row['id'] }}">
          <td>{{ $row['id'] }}</td>
              <td class="font-monospace small">{{ $row['dataset_id'] ?? '—' }}</td>
          <td style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            {{ $row['title'] ?? '—' }}
          </td>
          <td>
            @if(!empty($row['rest_url']))
              <a href="{{ $row['rest_url'] }}" target="_blank" rel="noopener">открыть</a>
            @else — @endif
          </td>
          <td>{{ $row['updated_at'] ?? '—' }}</td>
          <td>{{ $row['inserted_at'] ?? '—' }}</td>
          <td>
            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#raw-{{ $row['id'] }}-{{ md5($row['dataset_id'] ?? (string)$row['id']) }}">JSON</button>
          </td>
        </tr>
        <tr class="collapse" id="raw-{{ $row['id'] }}-{{ md5($row['dataset_id'] ?? (string)$row['id']) }}">
          <td colspan="7">
                <pre class="mb-0 small" style="max-height:260px;overflow:auto">{{ json_encode($row['raw'] ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) }}</pre>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="text-center text-muted">нет данных</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('osdrFilters');
  const table = document.getElementById('osdrTable');
  const tbody = table.querySelector('tbody');

  // Клиентская сортировка по клику по заголовку
  table.querySelectorAll('th.sortable').forEach(th => {
    th.style.cursor = 'pointer';
    th.addEventListener('click', () => {
      const col = th.dataset.col;
      const current = th.dataset.dir === 'asc' ? 'asc' : (th.dataset.dir === 'desc' ? 'desc' : null);
      const nextDir = current === 'asc' ? 'desc' : 'asc';
      table.querySelectorAll('th.sortable').forEach(h => h.removeAttribute('data-dir'));
      th.dataset.dir = nextDir;

      // собираем строки (только основные, collapse оставляем привязанными)
      const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => !r.classList.contains('collapse') && r.dataset.id);
      rows.sort((a, b) => {
        const idx = { id:0, dataset_id:1, title:2, updated_at:4, inserted_at:5 }[col] ?? 0;
        const av = (a.children[idx].textContent || '').trim();
        const bv = (b.children[idx].textContent || '').trim();
        if (col === 'id') {
          return (parseInt(av,10)||0) - (parseInt(bv,10)||0) * (nextDir === 'asc' ? 1 : -1);
        }
        const cmp = av.localeCompare(bv, 'ru', { sensitivity:'base' });
        return nextDir === 'asc' ? cmp : -cmp;
      });
      // перестраиваем тело
      const all = Array.from(tbody.querySelectorAll('tr'));
      tbody.innerHTML = '';
      rows.forEach(r => {
        const id = r.dataset.id;
        const detail = all.find(x => x.id && x.id.startsWith('raw-'+id+'-')) || null;
        tbody.appendChild(r);
        if (detail) tbody.appendChild(detail);
      });
    });
  });

  // Сабмит фильтров через GET, сервер уже учитывает limit и q в OsdrController
  form.addEventListener('submit', ev => {
    ev.preventDefault();
    const params = new URLSearchParams(new FormData(form)).toString();
    const base = window.location.pathname.replace(/\\?.*$/, '');
    window.location.href = base + '?' + params;
  });
});
</script>
@endsection
