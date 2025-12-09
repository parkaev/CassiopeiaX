<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Space Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <style>
    #map{height:340px}
    
    /* Animations */
    @keyframes fadeInDown {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.5; }
    }
    .fade-in, .card, .border.rounded { 
      animation: fadeInDown 0.3s ease-out forwards; 
    }
    .row > *:nth-child(1) .card, .row > *:nth-child(1) .border.rounded { animation-delay: 0s; }
    .row > *:nth-child(2) .card, .row > *:nth-child(2) .border.rounded { animation-delay: 0.05s; }
    .row > *:nth-child(3) .card, .row > *:nth-child(3) .border.rounded { animation-delay: 0.1s; }
    
    /* Loading animation */
    .loading { animation: pulse 1.5s infinite; color: #6c757d; }
    .spinner-dots::after {
      content: '';
      animation: dots 1.5s steps(4, end) infinite;
    }
    @keyframes dots {
      0% { content: ''; }
      25% { content: '.'; }
      50% { content: '..'; }
      75% { content: '...'; }
    }
  </style>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-body-tertiary sticky-top shadow-sm">
  <div class="container">
    <a class="navbar-brand" href="/dashboard">CassiopeiaX</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
      <div class="navbar-nav">
        <a class="nav-link" href="/dashboard">Dashboard</a>
        <a class="nav-link" href="/astronomy">Astronomy</a>
        <a class="nav-link" href="/iss">ISS</a>
        <a class="nav-link" href="/osdr">OSDR</a>
        <a class="nav-link" href="/cms">CMS</a>
        <a class="nav-link" href="/telemetry">Telemetry</a>
      </div>
    </div>
  </div>
</nav>
@yield('content')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
