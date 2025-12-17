@extends('layouts.app')

@section('content')
<div class="container pb-5" style="max-width: 1400px; margin: 0 auto;">
  <h3 class="mb-4">Space Dashboard</h3>

  <div class="row g-3">
    {{-- Колонка: МКС --}}
    <div class="col-lg-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title m-0">МКС Данные</h5>
            <a href="/iss" class="btn btn-sm btn-outline-primary">Подробнее →</a>
          </div>
          @if(!empty($iss['payload']))
            <table class="table table-sm">
              <tr><td class="text-muted">Широта</td><td>{{ number_format($iss['payload']['latitude'] ?? 0, 4) }}°</td></tr>
              <tr><td class="text-muted">Долгота</td><td>{{ number_format($iss['payload']['longitude'] ?? 0, 4) }}°</td></tr>
              <tr><td class="text-muted">Скорость</td><td>{{ number_format($iss['payload']['velocity'] ?? 0, 0, '', ' ') }} км/ч</td></tr>
              <tr><td class="text-muted">Высота</td><td>{{ number_format($iss['payload']['altitude'] ?? 0, 2) }} км</td></tr>
              <tr><td class="text-muted">Видимость</td><td>{{ $iss['payload']['visibility'] ?? '—' }}</td></tr>
            </table>
          @else
            <div class="text-muted">Нет данных</div>
          @endif
        </div>
      </div>
    </div>

    {{-- Колонка: OSDR --}}
    <div class="col-lg-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title m-0">OSDR Наборы</h5>
            <a href="/osdr" class="btn btn-sm btn-outline-primary">Подробнее →</a>
          </div>
          @if(!empty($osdr))
            <div class="list-group list-group-flush">
              @foreach(array_slice($osdr, 0, 5) as $item)
                @php
                  $raw = $item['raw'] ?? [];
                  $title = $item['title']
                    ?? ($raw['title'] ?? ($raw['name'] ?? ($raw['label'] ?? ($item['dataset_id'] ?? 'Без названия'))));
                  $secondary = $item['dataset_id']
                    ?? ($raw['accession'] ?? ($raw['studyId'] ?? '—'));
                @endphp
                <div class="list-group-item px-0 py-2">
                  <div class="small fw-bold text-truncate">{{ $title }}</div>
                  <small class="text-muted">{{ $secondary }}</small>
                </div>
              @endforeach
            </div>
          @else
            <div class="text-muted">Нет данных</div>
          @endif
        </div>
      </div>
    </div>

    {{-- Колонка: APOD --}}
    <div class="col-lg-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title m-0">APOD Сегодня</h5>
            <a href="/jwst" class="btn btn-sm btn-outline-primary">JWST →</a>
          </div>
          @if(!empty($apod))
            @if(isset($apod['media_type']) && $apod['media_type'] === 'image' && isset($apod['url']))
              <img src="{{ $apod['url'] }}" class="img-fluid rounded mb-2" alt="APOD" style="max-height: 200px; width: 100%; object-fit: cover;">
            @endif
            <div class="small">
              <strong>{{ $apod['title'] ?? 'NASA APOD' }}</strong>
              @if(isset($apod['date']))
                <div class="text-muted">{{ $apod['date'] }}</div>
              @endif
            </div>
          @else
            <div class="text-muted">Нет данных</div>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-3">

    {{-- НИЖНЯЯ ПОЛОСА: НОВАЯ ГАЛЕРЕЯ JWST --}}
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="card-title m-0">JWST — последние изображения</h5>
            <form id="jwstFilter" class="row g-2 align-items-center">
              <div class="col-auto">
                <select class="form-select form-select-sm" name="source" id="srcSel">
                  <option value="jpg" selected>Все JPG</option>
                  <option value="suffix">По суффиксу</option>
                  <option value="program">По программе</option>
                </select>
              </div>
              <div class="col-auto">
                <input type="text" class="form-control form-control-sm" name="suffix" id="suffixInp" placeholder="_cal / _thumb" style="width:140px;display:none">
                <input type="text" class="form-control form-control-sm" name="program" id="progInp" placeholder="2734" style="width:110px;display:none">
              </div>
              <div class="col-auto">
                <select class="form-select form-select-sm" name="instrument" style="width:130px">
                  <option value="">Любой инструмент</option>
                  <option>NIRCam</option><option>MIRI</option><option>NIRISS</option><option>NIRSpec</option><option>FGS</option>
                </select>
              </div>
              <div class="col-auto">
                <select class="form-select form-select-sm" name="perPage" style="width:90px">
                  <option>12</option><option selected>24</option><option>36</option><option>48</option>
                </select>
              </div>
              <div class="col-auto">
                <button class="btn btn-sm btn-primary" type="submit">Показать</button>
              </div>
            </form>
          </div>

          <style>
            .jwst-slider{position:relative}
            .jwst-track{
              display:flex; gap:.75rem; overflow:auto; scroll-snap-type:x mandatory; padding:.25rem;
            }
            .jwst-item{flex:0 0 180px; scroll-snap-align:start}
            .jwst-item img{width:100%; height:180px; object-fit:cover; border-radius:.5rem}
            .jwst-cap{font-size:.85rem; margin-top:.25rem}
            .jwst-nav{position:absolute; top:40%; transform:translateY(-50%); z-index:2}
            .jwst-prev{left:-.25rem} .jwst-next{right:-.25rem}
          </style>

          <div class="jwst-slider">
            <button class="btn btn-light border jwst-nav jwst-prev" type="button" aria-label="Prev">‹</button>
            <div id="jwstTrack" class="jwst-track border rounded"></div>
            <button class="btn btn-light border jwst-nav jwst-next" type="button" aria-label="Next">›</button>
          </div>

          <div id="jwstInfo" class="small text-muted mt-2"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function () {
  // ====== JWST ГАЛЕРЕЯ ======
  const track = document.getElementById('jwstTrack');
  const info  = document.getElementById('jwstInfo');
  const form  = document.getElementById('jwstFilter');
  const srcSel = document.getElementById('srcSel');
  const sfxInp = document.getElementById('suffixInp');
  const progInp= document.getElementById('progInp');

  function toggleInputs(){
    sfxInp.style.display  = (srcSel.value==='suffix')  ? '' : 'none';
    progInp.style.display = (srcSel.value==='program') ? '' : 'none';
  }
  srcSel.addEventListener('change', toggleInputs); toggleInputs();

  async function loadFeed(qs){
    track.innerHTML = '<div class="p-3 text-muted">Загрузка…</div>';
    info.textContent= '';
    try{
      const url = '/api/jwst/feed?'+new URLSearchParams(qs).toString();
      const r = await fetch(url);
      const js = await r.json();
      track.innerHTML = '';
      (js.items||[]).forEach(it=>{
        const fig = document.createElement('figure');
        fig.className = 'jwst-item m-0';
        fig.innerHTML = `
          <a href="${it.link||it.url}" target="_blank" rel="noreferrer">
            <img loading="lazy" src="${it.url}" alt="JWST">
          </a>
          <figcaption class="jwst-cap">${(it.caption||'').replaceAll('<','&lt;')}</figcaption>`;
        track.appendChild(fig);
      });
      info.textContent = `Источник: ${js.source} · Показано ${js.count||0}`;
    }catch(e){
      track.innerHTML = '<div class="p-3 text-danger">Ошибка загрузки</div>';
    }
  }

  form.addEventListener('submit', function(ev){
    ev.preventDefault();
    const fd = new FormData(form);
    const q = Object.fromEntries(fd.entries());
    loadFeed(q);
  });

  // навигация
  document.querySelector('.jwst-prev').addEventListener('click', ()=> track.scrollBy({left:-600, behavior:'smooth'}));
  document.querySelector('.jwst-next').addEventListener('click', ()=> track.scrollBy({left: 600, behavior:'smooth'}));

  // стартовые данные
  loadFeed({source:'jpg', perPage:24});
});
</script>
@endsection
