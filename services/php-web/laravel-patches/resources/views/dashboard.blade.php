@extends('layouts.app')

@section('content')
<div class="container pb-5 mt-3">
  {{-- верхние карточки --}}
  <div class="row g-3 mb-2">
    <div class="col-6"><div class="border rounded p-2 text-center">
      <div class="small text-muted">Скорость МКС</div>
      <div class="fs-4" id="issSpeed">{{ isset(($iss['payload'] ?? [])['velocity']) ? number_format($iss['payload']['velocity'],0,'',' ') : '—' }}</div>
    </div></div>
    <div class="col-6"><div class="border rounded p-2 text-center">
      <div class="small text-muted">Высота МКС</div>
      <div class="fs-4" id="issAlt">{{ isset(($iss['payload'] ?? [])['altitude']) ? number_format($iss['payload']['altitude'],0,'',' ') : '—' }}</div>
    </div></div>
  </div>

  <style>
    #jwstCol { 
      max-width: 0; 
      opacity: 0; 
      overflow: hidden; 
      padding: 0 !important;
      transition: max-width 0.4s ease, opacity 0.4s ease, padding 0.4s ease;
    }
    #jwstCol.show { 
      max-width: 50%; 
      opacity: 1; 
      padding: 0 calc(var(--bs-gutter-x) * .5) !important;
    }
    #issCol {
      transition: max-width 0.4s ease;
    }
    @media (max-width: 991.98px) {
      #jwstCol.show { max-width: 100%; }
    }
  </style>

  <div class="row g-3" id="mainRow">
    {{-- левая колонка: JWST наблюдение (скрыта по умолчанию) --}}
    <div class="col-lg-6" id="jwstCol">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title m-0">JWST — выбранное наблюдение</h5>
            <button type="button" class="btn-close" id="closeJwst" aria-label="Закрыть"></button>
          </div>
          <div id="jwstViewer" class="mt-3"></div>
        </div>
      </div>
    </div>

    {{-- правая колонка: карта МКС (на всю ширину по умолчанию) --}}
    <div class="col-lg" id="issCol">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h5 class="card-title">МКС — положение и движение</h5>
          <div id="map" class="rounded mb-2 border" style="height:300px"></div>
          <div class="row g-2">
            <div class="col-6"><canvas id="issSpeedChart" height="110"></canvas></div>
            <div class="col-6"><canvas id="issAltChart"   height="110"></canvas></div>
          </div>
        </div>
      </div>
    </div>

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
  // ====== карта и графики МКС ======
  if (typeof L !== 'undefined' && typeof Chart !== 'undefined') {
    const last = @json(($iss['payload'] ?? []));
    let lat0 = Number(last.latitude || 0), lon0 = Number(last.longitude || 0);
    const map = L.map('map', { attributionControl:false }).setView([lat0||0, lon0||0], lat0?3:2);
    window._issMap = map;
    L.tileLayer('https://{s}.tile.openstreetmap.de/{z}/{x}/{y}.png', { noWrap:true }).addTo(map);
    const trail  = L.polyline([], {weight:3, color:'#0d6efd'}).addTo(map);
    const marker = L.marker([lat0||0, lon0||0]).addTo(map).bindPopup('МКС');

    const points = [];
    const maxPoints = 240;
    let lastLat = null, lastLon = null;

    const speedChart = new Chart(document.getElementById('issSpeedChart'), {
      type: 'line', data: { labels: [], datasets: [{ label: 'Скорость', data: [], borderColor: '#0d6efd', tension: 0.1 }] },
      options: { responsive: true, maintainAspectRatio: false, scales: { x: { display: false } } }
    });
    const altChart = new Chart(document.getElementById('issAltChart'), {
      type: 'line', data: { labels: [], datasets: [{ label: 'Высота', data: [], borderColor: '#198754', tension: 0.1 }] },
      options: { responsive: true, maintainAspectRatio: false, scales: { x: { display: false } } }
    });

    async function updateISS() {
      try {
        const r = await fetch('/api/iss/last');
        const data = await r.json();
        const p = data.payload;
        
        if (p && p.latitude && p.longitude) {
          // Добавляем точку только если координаты изменились
          if (lastLat !== p.latitude || lastLon !== p.longitude) {
            points.push({
              lat: p.latitude,
              lon: p.longitude,
              velocity: p.velocity,
              altitude: p.altitude,
              time: new Date().toLocaleTimeString()
            });
            
            lastLat = p.latitude;
            lastLon = p.longitude;
            
            if (points.length > maxPoints) points.shift();
            
            const coords = points.map(pt => [pt.lat, pt.lon]);
            trail.setLatLngs(coords);
            
            speedChart.data.labels = points.map(pt => pt.time);
            speedChart.data.datasets[0].data = points.map(pt => pt.velocity);
            speedChart.update('none');
            
            altChart.data.labels = points.map(pt => pt.time);
            altChart.data.datasets[0].data = points.map(pt => pt.altitude);
            altChart.update('none');
          }
          
          // Маркер и карточки обновляем всегда
          marker.setLatLng([p.latitude, p.longitude]);
          map.setView([p.latitude, p.longitude], map.getZoom());
          document.getElementById('issSpeed').textContent = Math.round(p.velocity).toLocaleString('ru-RU');
          document.getElementById('issAlt').textContent = Math.round(p.altitude).toLocaleString('ru-RU');
        }
      } catch(e) { console.error('ISS update error:', e); }
    }
    
    updateISS();
    setInterval(updateISS, 15000);
  }

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
    track.innerHTML = '<div class="p-3 text-muted loading">Загрузка<span class="spinner-dots"></span></div>';
    info.textContent= '';
    try{
      const url = '/api/jwst/feed?'+new URLSearchParams(qs).toString();
      const r = await fetch(url);
      const js = await r.json();
      track.innerHTML = '';
      (js.items||[]).forEach(it=>{
        const fig = document.createElement('figure');
        fig.className = 'jwst-item m-0';
        fig.style.cursor = 'pointer';
        fig.innerHTML = `
          <img loading="lazy" src="${it.url}" alt="JWST">
          <figcaption class="jwst-cap">${(it.caption||'').replaceAll('<','&lt;')}</figcaption>`;
        fig.addEventListener('click', () => showJwstImage(it));
        track.appendChild(fig);
      });
      info.textContent = `Источник: ${js.source} · Показано ${js.count||0}`;
    }catch(e){
      track.innerHTML = '<div class="p-3 text-danger">Ошибка загрузки</div>';
    }
  }

  function showJwstImage(item) {
    const jwstCol = document.getElementById('jwstCol');
    const viewer = document.getElementById('jwstViewer');
    const instruments = Array.isArray(item.inst) ? item.inst.join(', ') : (item.inst || '—');
    
    // Show JWST panel
    if (!jwstCol.classList.contains('show')) {
      jwstCol.classList.add('show');
      setTimeout(() => {
        if (window._issMap) window._issMap.invalidateSize();
      }, 450);
    }
    
    viewer.innerHTML = `
      <div class="text-center">
        <img src="${item.url}" alt="JWST" class="rounded mb-3" style="width:100%; height:280px; object-fit:contain; background:#f8f9fa">
      </div>
      <table class="table table-sm mb-3">
        <tr><th>Наблюдение</th><td>${item.obs || '—'}</td></tr>
        <tr><th>Инструмент</th><td>${instruments}</td></tr>
        <tr><th>Программа</th><td>${item.program || '—'}</td></tr>
        <tr><th>Суффикс</th><td><code>${item.suffix || '—'}</code></td></tr>
      </table>
      <div class="text-center">
        <a href="${item.url}" download class="btn btn-success btn-sm">Скачать</a>
      </div>
    `;
  }

  // Close JWST viewer
  document.getElementById('closeJwst').addEventListener('click', function() {
    const jwstCol = document.getElementById('jwstCol');
    jwstCol.classList.remove('show');
    setTimeout(() => {
      if (window._issMap) window._issMap.invalidateSize();
    }, 450);
  });

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

<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.L && window._issMapTileLayer) {
    const map  = window._issMap;
    let   tl   = window._issMapTileLayer;
    tl.on('tileerror', () => {
      try {
        map.removeLayer(tl);
      } catch(e) {}
      tl = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: ''});
      tl.addTo(map);
      window._issMapTileLayer = tl;
    });
  }
});
</script>
