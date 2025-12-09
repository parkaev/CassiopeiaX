@extends('layouts.app')

@section('content')
<div class="container pb-5">
  <div class="row">
    <div class="col-12">
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
@endsection
