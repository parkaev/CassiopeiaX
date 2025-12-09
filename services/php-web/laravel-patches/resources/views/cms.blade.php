@extends('layouts.app')

@section('content')
<div class="container pb-5">
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

  <div class="card mt-3">
    <div class="card-header fw-semibold">CMS — not found</div>
    <div class="card-body">
      @if($dashboard_not_found)
        {!! $dashboard_not_found !!}
      @else
        <div class="text-muted">блок не найден</div>
      @endif
    </div>
  </div>
</div>
@endsection
