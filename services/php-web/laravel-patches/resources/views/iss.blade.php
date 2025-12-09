@extends('layouts.app')

@section('content')
<div class="container py-4 mt-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">МКС данные</h3>
    <button id="refreshBtn" class="btn btn-primary">
      <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" class="me-1">
        <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
        <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
      </svg>
      Обновить
    </button>
  </div>

  <div class="row g-3">
    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h5 class="card-title">Последний снимок</h5>
          <div id="lastData">
            @if(!empty($last['payload']))
              <ul class="list-group">
                <li class="list-group-item">Широта {{ $last['payload']['latitude'] ?? '—' }}</li>
                <li class="list-group-item">Долгота {{ $last['payload']['longitude'] ?? '—' }}</li>
                <li class="list-group-item">Высота км {{ $last['payload']['altitude'] ?? '—' }}</li>
                <li class="list-group-item">Скорость км/ч {{ $last['payload']['velocity'] ?? '—' }}</li>
                <li class="list-group-item">Время {{ $last['fetched_at'] ?? '—' }}</li>
              </ul>
            @else
              <div class="text-muted">нет данных</div>
            @endif
          </div>
          <div class="mt-3"><code>{{ $base }}/last</code></div>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h5 class="card-title">Тренд движения</h5>
          <div id="trendData">
            @if(!empty($trend))
              <ul class="list-group">
                <li class="list-group-item">Движение {{ ($trend['movement'] ?? false) ? 'да' : 'нет' }}</li>
                <li class="list-group-item">Смещение км {{ number_format($trend['delta_km'] ?? 0, 3, '.', ' ') }}</li>
                <li class="list-group-item">Интервал сек {{ $trend['dt_sec'] ?? 0 }}</li>
                <li class="list-group-item">Скорость км/ч {{ $trend['velocity_kmh'] ?? '—' }}</li>
              </ul>
            @else
              <div class="text-muted">нет данных</div>
            @endif
          </div>
          <div class="mt-3"><code>{{ $base }}/iss/trend</code></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('refreshBtn').addEventListener('click', async function() {
  const btn = this;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Обновление...';
  
  try {
    const [lastRes, trendRes] = await Promise.all([
      fetch('/api/iss/last'),
      fetch('/api/iss/trend')
    ]);
    
    const last = await lastRes.json();
    const trend = await trendRes.json();
    
    const p = last.payload || {};
    document.getElementById('lastData').innerHTML = p.latitude ? `
      <ul class="list-group">
        <li class="list-group-item">Широта ${p.latitude || '—'}</li>
        <li class="list-group-item">Долгота ${p.longitude || '—'}</li>
        <li class="list-group-item">Высота км ${p.altitude || '—'}</li>
        <li class="list-group-item">Скорость км/ч ${p.velocity || '—'}</li>
        <li class="list-group-item">Время ${last.fetched_at || '—'}</li>
      </ul>
    ` : '<div class="text-muted">нет данных</div>';
    
    document.getElementById('trendData').innerHTML = trend.movement !== undefined ? `
      <ul class="list-group">
        <li class="list-group-item">Движение ${trend.movement ? 'да' : 'нет'}</li>
        <li class="list-group-item">Смещение км ${(trend.delta_km || 0).toFixed(3)}</li>
        <li class="list-group-item">Интервал сек ${trend.dt_sec || 0}</li>
        <li class="list-group-item">Скорость км/ч ${trend.velocity_kmh || '—'}</li>
      </ul>
    ` : '<div class="text-muted">нет данных</div>';
    
  } catch(e) {
    alert('Ошибка обновления: ' + e.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" class="me-1"><path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/><path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/></svg>Обновить';
  }
});
</script>
@endsection
