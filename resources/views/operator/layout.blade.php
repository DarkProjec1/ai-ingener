<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Панель оператора') — Вкусная осень</title>
    <style>
        :root { --bg: #0f1419; --card: #1a2332; --border: #2d3a4f; --text: #e7ecf3; --muted: #8b9bb4; --accent: #3b82f6; --success: #22c55e; --danger: #ef4444; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .header { background: var(--card); border-bottom: 1px solid var(--border); padding: 12px 24px; display: flex; align-items: center; gap: 24px; }
        .header a { color: var(--muted); text-decoration: none; }
        .header a:hover, .header a.active { color: var(--accent); }
        .header .brand { font-weight: 700; color: var(--text); margin-right: auto; }
        .container { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--border); }
        th { color: var(--muted); font-weight: 500; font-size: 13px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; }
        .badge.open { background: #1e3a5f; color: #60a5fa; }
        .badge.closed { background: #1a3a2a; color: #4ade80; }
        .btn { display: inline-block; padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer; font-size: 14px; text-decoration: none; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border); }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
        .alert-success { background: #14532d; color: #86efac; }
        .alert-error { background: #7f1d1d; color: #fca5a5; }
        .msg { padding: 10px 14px; border-radius: 10px; margin-bottom: 8px; max-width: 80%; }
        .msg.user { background: #1e293b; margin-right: auto; }
        .msg.bot { background: #1e3a5f; margin-right: auto; }
        .msg.operator { background: #14532d; margin-left: auto; }
        .msg .meta { font-size: 11px; color: var(--muted); margin-bottom: 4px; }
        textarea { width: 100%; min-height: 100px; background: var(--bg); border: 1px solid var(--border); color: var(--text); border-radius: 8px; padding: 12px; font-family: inherit; }
        input[type=email], input[type=password] { width: 100%; padding: 10px 12px; background: var(--bg); border: 1px solid var(--border); color: var(--text); border-radius: 8px; margin-bottom: 12px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; }
        .stat { text-align: center; }
        .stat .num { font-size: 32px; font-weight: 700; }
        .stat .label { color: var(--muted); font-size: 13px; }
        .tabs a { margin-right: 12px; }
    </style>
</head>
<body>
    @auth
    <div class="header">
        <span class="brand">🍂 Вкусная осень — операторы</span>
        <a href="{{ route('operator.tickets.index') }}" class="{{ request()->routeIs('operator.tickets.*') ? 'active' : '' }}">Обращения</a>
        <a href="{{ route('operator.stats') }}" class="{{ request()->routeIs('operator.stats') ? 'active' : '' }}">Статистика</a>
        <form action="{{ route('logout') }}" method="POST" style="display:inline">@csrf<button class="btn btn-ghost" type="submit">Выйти</button></form>
    </div>
    @endauth
    <div class="container">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
        @yield('content')
    </div>
</body>
</html>
