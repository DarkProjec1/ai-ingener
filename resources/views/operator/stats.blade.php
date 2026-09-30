@extends('operator.layout')
@section('title', 'Статистика')
@section('content')
<h1 style="margin-bottom:16px">Статистика</h1>
<div class="stats-grid">
    <div class="card stat">
        <div class="num">{{ $botResolved }}</div>
        <div class="label">Бот закрыл сам</div>
    </div>
    <div class="card stat">
        <div class="num">{{ $escalated }}</div>
        <div class="label">Передано операторам</div>
    </div>
    <div class="card stat">
        <div class="num">{{ $openTickets }}</div>
        <div class="label">Открытых тикетов</div>
    </div>
    <div class="card stat">
        <div class="num">{{ $closedTickets }}</div>
        <div class="label">Закрытых тикетов</div>
    </div>
    <div class="card stat">
        <div class="num" style="font-size:24px">{{ $avgResponseHuman }}</div>
        <div class="label">Среднее время ответа оператора</div>
    </div>
</div>
<p style="color:var(--muted);margin-top:16px;font-size:13px">Время ответа считается от создания тикета до первого ответа оператора (без учёта рабочего времени).</p>
@endsection
