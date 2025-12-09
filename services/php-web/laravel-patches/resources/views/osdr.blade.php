@extends('layouts.app')

@section('content')
<div class="container py-3 mt-3">
  <h3 class="mb-3">NASA OSDR</h3>
  <div class="small text-muted mb-2">Источник {{ $src }}</div>

  <div class="mb-3">
    <input type="text" id="searchInput" class="form-control" placeholder="Поиск по названию...">
  </div>

  <div class="table-responsive">
    <table class="table table-sm table-striped align-middle" id="osdrTable">
      <thead>
        <tr>
          <th class="sortable" data-col="0"># <span class="sort-icon">⇅</span></th>
          <th class="sortable" data-col="1">dataset_id <span class="sort-icon">⇅</span></th>
          <th class="sortable" data-col="2">title <span class="sort-icon">⇅</span></th>
          <th>REST_URL</th>
          <th class="sortable" data-col="4">updated_at <span class="sort-icon">⇅</span></th>
          <th class="sortable" data-col="5">inserted_at <span class="sort-icon">⇅</span></th>
          <th>raw</th>
        </tr>
      </thead>
      <tbody id="osdrBody">
      @forelse($items as $row)
        <tr>
          <td>{{ $row['id'] }}</td>
          <td>{{ $row['dataset_id'] ?? '—' }}</td>
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
        <tr class="collapse raw-row" id="raw-{{ $row['id'] }}-{{ md5($row['dataset_id'] ?? (string)$row['id']) }}">
          <td colspan="7">
            <pre class="mb-0" style="max-height:260px;overflow:auto">{{ json_encode($row['raw'] ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) }}</pre>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="text-center text-muted">нет данных</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>

<style>
.sortable { cursor: pointer; user-select: none; }
.sortable:hover { background: #f0f0f0; }
.sort-icon { opacity: 0.3; font-size: 0.8em; }
.sortable.asc .sort-icon::after { content: '↑'; opacity: 1; }
.sortable.desc .sort-icon::after { content: '↓'; opacity: 1; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const search = document.getElementById('searchInput');
  const tbody = document.getElementById('osdrBody');
  const rows = Array.from(tbody.querySelectorAll('tr:not(.raw-row)'));
  
  search.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    rows.forEach(row => {
      const title = row.cells[2]?.textContent.toLowerCase() || '';
      const match = title.includes(q);
      row.style.display = match ? '' : 'none';
      const nextRow = row.nextElementSibling;
      if (nextRow?.classList.contains('raw-row')) {
        nextRow.style.display = match ? '' : 'none';
      }
    });
  });

  document.querySelectorAll('.sortable').forEach(th => {
    th.addEventListener('click', function() {
      const col = parseInt(this.dataset.col);
      const asc = !this.classList.contains('asc');
      document.querySelectorAll('.sortable').forEach(t => t.classList.remove('asc', 'desc'));
      this.classList.add(asc ? 'asc' : 'desc');
      
      rows.sort((a, b) => {
        const aVal = a.cells[col]?.textContent.trim() || '';
        const bVal = b.cells[col]?.textContent.trim() || '';
        const aNum = parseFloat(aVal), bNum = parseFloat(bVal);
        if (!isNaN(aNum) && !isNaN(bNum)) return asc ? aNum - bNum : bNum - aNum;
        return asc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
      });
      
      rows.forEach(row => {
        const rawRow = row.nextElementSibling?.classList.contains('raw-row') ? row.nextElementSibling : null;
        tbody.appendChild(row);
        if (rawRow) tbody.appendChild(rawRow);
      });
    });
  });
});
</script>
@endsection
