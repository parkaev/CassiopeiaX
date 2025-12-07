@extends('layouts.app')

@section('content')
<div class="container pb-5">
  {{-- верхние карточки --}}
  <div class="row g-3 mb-2">
    <div class="col-6 col-md-3"><div class="border rounded p-2 text-center">
      <div class="small text-muted">Скорость МКС</div>
      <div class="fs-4" id="issSpeed">{{ isset(($iss['payload'] ?? [])['velocity']) ? number_format($iss['payload']['velocity'],0,'',' ') : '—' }}</div>
    </div></div>
    <div class="col-6 col-md-3"><div class="border rounded p-2 text-center">
      <div class="small text-muted">Высота МКС</div>
      <div class="fs-4" id="issAlt">{{ isset(($iss['payload'] ?? [])['altitude']) ? number_format($iss['payload']['altitude'],0,'',' ') : '—' }}</div>
    </div></div>
  </div>

  <div class="row g-3">
    {{-- левая колонка: JWST наблюдение (как раньше было под APOD можно держать своим блоком) --}}
    <div class="col-lg-7">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h5 class="card-title">JWST — выбранное наблюдение</h5>
          <div class="text-muted">Этот блок остаётся как был (JSON/сводка). Основная галерея ниже.</div>
        </div>
      </div>
    </div>

    {{-- правая колонка: карта МКС --}}
    <div class="col-lg-5">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="card-title mb-0">МКС — положение и движение</h5>
            <button id="issRefreshBtn" class="btn btn-sm btn-outline-primary" title="Обновить данные">
              <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
              </svg>
            </button>
          </div>
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
    
    document.getElementById('issRefreshBtn').addEventListener('click', updateISS);
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

    <!-- ASTRO — события -->
    <div class="col-12 order-first mt-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="card-title m-0">Астрономические события (AstronomyAPI)</h5>
            <form id="astroForm" class="row g-2 align-items-center">
              <div class="col-auto">
                <input type="number" step="0.0001" class="form-control form-control-sm" name="lat" value="55.7558" placeholder="lat">
              </div>
              <div class="col-auto">
                <input type="number" step="0.0001" class="form-control form-control-sm" name="lon" value="37.6176" placeholder="lon">
              </div>
              <div class="col-auto">
                <input type="number" min="1" max="30" class="form-control form-control-sm" name="days" value="7" style="width:90px" title="дней">
              </div>
              <div class="col-auto">
                <button class="btn btn-sm btn-primary" type="submit">Показать</button>
              </div>
            </form>
          </div>

          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr><th>#</th><th>Тело</th><th>Событие</th><th>Когда (UTC)</th><th>Дополнительно</th></tr>
              </thead>
              <tbody id="astroBody">
                <tr><td colspan="5" class="text-muted">нет данных</td></tr>
              </tbody>
            </table>
          </div>

          <details class="mt-2">
            <summary>Полный JSON</summary>
            <pre id="astroRaw" class="bg-light rounded p-2 small m-0" style="white-space:pre-wrap"></pre>
          </details>
        </div>
      </div>
    </div>

    <script>
      document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('astroForm');
        const body = document.getElementById('astroBody');
        const raw  = document.getElementById('astroRaw');

        async function load(q){
          body.innerHTML = '<tr><td colspan="5" class="text-muted">Загрузка…</td></tr>';
          const url = '/api/astro/events?' + new URLSearchParams(q).toString();
          try{
            const r  = await fetch(url);
            const js = await r.json();
            raw.textContent = JSON.stringify(js, null, 2);

            const events = js.events || [];
            if (!events.length) {
              body.innerHTML = '<tr><td colspan="5" class="text-muted">события не найдены</td></tr>';
              return;
            }
            body.innerHTML = events.map((e,i)=>`
              <tr>
                <td>${i+1}</td>
                <td>${e.name || '—'}</td>
                <td>${e.type || '—'}</td>
                <td><code>${e.date || '—'}</code></td>
                <td><pre class="m-0 small">${(e.note || '').replace(/</g,'&lt;')}</pre></td>
              </tr>
            `).join('');
          }catch(e){
            body.innerHTML = '<tr><td colspan="5" class="text-danger">ошибка загрузки</td></tr>';
          }
        }

        form.addEventListener('submit', ev=>{
          ev.preventDefault();
          const q = Object.fromEntries(new FormData(form).entries());
          load(q);
        });

        // автозагрузка
        load({lat: form.lat.value, lon: form.lon.value, days: form.days.value});
      });
    </script>


{{-- ===== CMS Blocks ===== --}}
<div class="card mt-3">
  <div class="card-header fw-semibold">CMS — welcome</div>
  <div class="card-body">
    @if($dashboard_welcome)
      {!! $dashboard_welcome !!}
    @else
      <div class="text-muted">блок не найден</div>
    @endif
  </div>
</div>

<div class="card mt-3">
  <div class="card-header fw-semibold">CMS — unsafe</div>
  <div class="card-body">
    @if($dashboard_unsafe)
      {!! $dashboard_unsafe !!}
    @else
      <div class="text-muted">блок не найден</div>
    @endif
  </div>
</div>

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

<div class="card mt-3">
  <div class="card-header fw-semibold">CMS — not found</div>
  <div class="card-body">
    @if(!empty($dashboard_not_found))
      {!! $dashboard_not_found !!}
    @else
      <div class="text-muted">блок не найден</div>
    @endif
  </div>
</div>
