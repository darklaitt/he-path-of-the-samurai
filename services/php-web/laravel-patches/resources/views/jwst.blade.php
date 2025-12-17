@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 1400px; margin: 0 auto;">
  <div class="mb-3">
    <h3>James Webb Space Telescope — Галерея</h3>
  </div>
  
  <div class="row mb-4">
    <div class="col-md-12">
      <div class="card shadow-sm" style="position: relative; z-index: 1;">
        <div class="card-body">
          <div class="d-flex gap-2 align-items-center flex-wrap">
            <input type="text" id="search" class="form-control" placeholder="Поиск изображений..." style="max-width: 300px;">
            <select id="instrument" class="form-select" style="max-width: 200px;">
              <option value="">Все инструменты</option>
              <option value="NIRCam">NIRCam</option>
              <option value="MIRI">MIRI</option>
              <option value="NIRISS">NIRISS</option>
              <option value="NIRSpec">NIRSpec</option>
              <option value="FGS">FGS</option>
            </select>
            <button id="loadBtn" class="btn btn-primary">Загрузить</button>
            <span id="status" class="text-muted"></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="gallery" class="row g-3">
    <!-- Изображения загружаются через API -->
  </div>

  <div id="loading" class="text-center py-5" style="display:none;">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">Загрузка...</span>
    </div>
  </div>
</div>

<style>
.jwst-card {
  border: 1px solid #bdc3c7;
  border-radius: 2px;
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s;
}
.jwst-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.jwst-img {
  width: 100%;
  height: 200px;
  object-fit: cover;
  cursor: pointer;
}
.jwst-info {
  padding: 10px;
  background: #ecf0f1;
}
.jwst-title {
  font-weight: 600;
  font-size: 14px;
  color: #2c3e50;
  margin-bottom: 5px;
}
.jwst-meta {
  font-size: 12px;
  color: #7f8c8d;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const gallery = document.getElementById('gallery');
  const loading = document.getElementById('loading');
  const status = document.getElementById('status');
  const loadBtn = document.getElementById('loadBtn');
  const searchInput = document.getElementById('search');
  const instrumentSelect = document.getElementById('instrument');

  async function loadImages() {
    loading.style.display = 'block';
    gallery.innerHTML = '';
    status.textContent = '';

    const params = new URLSearchParams({
      source: 'jpg',
      perPage: 24,
      instrument: instrumentSelect.value
    });

    try {
      const response = await fetch(`/api/jwst/feed?${params}`);
      const data = await response.json();
      
      loading.style.display = 'none';

      if (!data.items || data.items.length === 0) {
        status.textContent = 'Изображения не найдены';
        return;
      }

      status.textContent = `Загружено: ${data.items.length}`;

      data.items.forEach(item => {
        const col = document.createElement('div');
        col.className = 'col-md-3 col-sm-6';
        
        col.innerHTML = `
          <div class="jwst-card">
            <img src="${item.url}" class="jwst-img" alt="${item.obs || item.caption}" 
                 onclick="window.open('${item.url}', '_blank')">
            <div class="jwst-info">
              <div class="jwst-title">${item.obs || 'JWST Image'}</div>
              ${item.program ? `<div class="jwst-meta">Program: ${item.program}</div>` : ''}
              ${item.suffix ? `<div class="jwst-meta">Type: ${item.suffix}</div>` : ''}
            </div>
          </div>
        `;
        
        gallery.appendChild(col);
      });
    } catch (error) {
      loading.style.display = 'none';
      status.textContent = 'Ошибка загрузки: ' + error.message;
      console.error('JWST API error:', error);
    }
  }

  loadBtn.addEventListener('click', loadImages);
  
  // Автозагрузка при открытии
  loadImages();
});
</script>
@endsection
