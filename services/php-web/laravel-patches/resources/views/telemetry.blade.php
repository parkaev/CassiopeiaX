@extends('layouts.app')

@section('content')
<div class="container pb-5 mt-3">
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="card-title m-0">Telemetry Legacy</h5>
        <a href="/telemetry/export" class="btn btn-success btn-sm">
          Скачать .xlsx
        </a>
      </div>

      @if(count($telemetry) > 0)
        <div class="table-responsive">
          <table class="table table-sm table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Дата и время</th>
                <th>Напряжение (V)</th>
                <th>Температура (°C)</th>
                <th>Файл источника</th>
              </tr>
            </thead>
            <tbody>
              @foreach($telemetry as $row)
                <tr>
                  <td>{{ $row->id }}</td>
                  <td><code>{{ $row->recorded_at }}</code></td>
                  <td>{{ $row->voltage }}</td>
                  <td>{{ $row->temp }}</td>
                  <td><small>{{ $row->source_file }}</small></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="text-muted text-center py-4">
          Данные телеметрии отсутствуют
        </div>
      @endif
    </div>
  </div>
</div>
@endsection
