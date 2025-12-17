<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Space Dashboard')</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #ecf0f1;
            color: #2c3e50;
            min-height: 100vh;
            line-height: 1.6;
        }

        /* Навигация - Flat Style */
        nav {
            background: #34495e;
            padding: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        nav .container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }
        
        nav h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #ecf0f1;
            padding: 1rem 0;
        }
        
        nav ul {
            display: flex;
            list-style: none;
            gap: 0;
        }
        
        nav a {
            color: #ecf0f1;
            text-decoration: none;
            padding: 1.2rem 1.5rem;
            display: block;
            transition: background 0.2s ease;
            border-bottom: 3px solid transparent;
        }
        
        nav a:hover {
            background: #2c3e50;
            border-bottom-color: #3498db;
        }
        
        nav a.active {
            background: #2c3e50;
            border-bottom-color: #3498db;
            font-weight: 600;
        }

        /* Контейнер */
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Карточки - Flat Style */
        .card {
            background: #fff;
            border: none;
            border-radius: 2px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
            transition: box-shadow 0.2s ease;
        }
        
        .card:hover {
            box-shadow: 0 3px 6px rgba(0,0,0,0.16);
        }

        .card h2, .card h3, .card h4, .card h5 {
            color: #2c3e50;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #3498db;
            font-weight: 600;
        }

        /* Кнопки - Flat Style */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 2px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        
        .btn-primary, .btn-outline-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover, .btn-outline-primary:hover {
            background: #2980b9;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }

        /* Таблицы - Flat Style */
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        
        thead {
            background: #34495e;
        }
        
        th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #ecf0f1;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #ecf0f1;
            color: #2c3e50;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .table-responsive {
            overflow-x: auto;
        }

        .table-hover tbody tr:hover {
            background: #f8f9fa;
        }

        /* Бейджи - Flat Style */
        .badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 2px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-success, .badge.bg-success {
            background: #27ae60 !important;
            color: white;
        }
        
        .badge-warning, .badge.bg-warning {
            background: #f39c12 !important;
            color: white;
        }
        
        .badge-danger, .badge.bg-danger {
            background: #e74c3c !important;
            color: white;
        }

        .badge-info, .badge.bg-info {
            background: #3498db !important;
            color: white;
        }

        .badge-secondary, .badge.bg-secondary {
            background: #95a5a6 !important;
            color: white;
        }

        /* Inputs - Flat Style */
        input, select, .form-control, .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #bdc3c7;
            border-radius: 2px;
            background: white;
            color: #2c3e50;
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }
        
        .form-control-sm, .form-select-sm {
            padding: 0.5rem;
            font-size: 0.9rem;
        }
        
        input:focus, select:focus, .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #3498db;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 600;
        }

        /* Грид */
        .row {
            display: flex;
            flex-wrap: wrap;
            /* Горизонтальные внешние отступы для имитации gutters, без отрицательного верхнего/нижнего отступа */
            margin: 0 -0.75rem;
        }
        
        .col, .col-12, .col-md-6, .col-md-4, .col-md-3, .col-auto {
            padding: 0.75rem;
        }
        
        .col-12 { width: 100%; }
        .col-md-6 { width: 50%; }
        .col-md-4 { width: 33.333%; }
        .col-md-3 { width: 25%; }
        .col-auto { width: auto; }
        
        @media (max-width: 768px) {
            .col-md-6, .col-md-4, .col-md-3 { width: 100%; }
        }
        
        .g-2 { gap: 0.5rem; }
        .g-3 { gap: 1rem; }

        /* Утилиты */
        .d-flex { display: flex; }
        .flex-column { flex-direction: column; }
        .justify-content-between { justify-content: space-between; }
        .align-items-center { align-items: center; }
        .align-items-end { align-items: flex-end; }
        .text-center { text-align: center; }
        .text-muted { color: #7f8c8d; }
        .mb-0 { margin-bottom: 0; }
        .mb-1 { margin-bottom: 0.5rem; }
        .mb-2 { margin-bottom: 0.75rem; }
        .mb-3 { margin-bottom: 1rem; }
        .mt-3 { margin-top: 1rem; }
        .mt-5 { margin-top: 2rem; }
        .py-4 { padding-top: 2rem; padding-bottom: 2rem; }
        .small { font-size: 0.875rem; }
        .h-100 { height: 100%; }

        /* Алерты - Flat Style */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 2px;
            margin-bottom: 1rem;
            border-left: 4px solid;
        }
        
        .alert-error {
            background: #fadbd8;
            border-left-color: #e74c3c;
            color: #c0392b;
        }
        
        .alert-success {
            background: #d4edda;
            border-left-color: #27ae60;
            color: #1e8449;
        }
        
        .alert-info {
            background: #d1ecf1;
            border-left-color: #3498db;
            color: #2874a6;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        /* Карта */
        #map {
            height: 400px;
            margin: 1rem 0;
            border-radius: 2px;
        }

        /* Загрузка */
        .loading {
            text-align: center;
            padding: 2rem;
            color: #7f8c8d;
        }

        /* Card body */
        .card-body {
            padding: 0;
        }

        .card-title {
            color: #2c3e50;
            font-size: 1.25rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #3498db;
            font-weight: 600;
            /* Не растягивать заголовок во flex-строке */
            flex: 0 0 auto;
            display: inline-block;
        }

        /* Footer */
        footer {
            margin-top: 3rem;
            padding: 2rem;
            text-align: center;
            color: #7f8c8d;
        }

        /* JWST Gallery */
        .jwst-card {
            position: relative;
            overflow: hidden;
            border-radius: 2px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            background: #fff;
        }
        
        .jwst-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .jwst-img {
            width: 100%;
            height: 220px;
            object-fit: cover;
        }
        
        .jwst-meta {
            padding: 10px;
            background: #34495e;
            color: white;
        }
        
        .jwst-meta small {
            display: block;
            color: #ecf0f1;
        }

        /* Sortable table headers */
        .sortable {
            cursor: pointer;
            user-select: none;
        }
        
        .sortable:hover {
            background: #2c3e50;
        }
    </style>
    @stack('styles')
</head>
<body>
    <nav>
        <div class="container">
            <h1>Space Dashboard</h1>
            <ul>
                <li><a href="/dashboard" class="{{ request()->is('dashboard') ? 'active' : '' }}">Dashboard</a></li>
                <li><a href="/iss" class="{{ request()->is('iss') ? 'active' : '' }}">ISS</a></li>
                <li><a href="/osdr" class="{{ request()->is('osdr') ? 'active' : '' }}">OSDR</a></li>
                <li><a href="/jwst" class="{{ request()->is('jwst') ? 'active' : '' }}">JWST</a></li>
                <li><a href="/astronomy" class="{{ request()->is('astronomy') ? 'active' : '' }}">Astronomy</a></li>
            </ul>
        </div>
    </nav>

    <div class="container fade-in">
        @yield('content')
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @stack('scripts')
</body>
</html>
