@extends('operator.layout')
@section('title', 'Обращение #'.$ticket->id)
@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <h1>Обращение #{{ $ticket->id }} <span class="badge {{ $ticket->status }}">{{ $ticket->status }}</span></h1>
    <div>
        @if($ticket->status === 'open')
        <form action="{{ route('operator.tickets.close', $ticket) }}" method="POST" style="display:inline" onsubmit="return confirm('Закрыть обращение?')">
            @csrf
            <button class="btn btn-danger" type="submit">Закрыть</button>
        </form>
        @endif
        <a href="{{ route('operator.tickets.index') }}" class="btn btn-ghost">← Назад</a>
    </div>
</div>

<div class="card">
    <p style="color:var(--muted);margin-bottom:8px">
        Участник: <strong>{{ $ticket->participant->first_name }} {{ $ticket->participant->last_name }}</strong>
        (@{{ $ticket->participant->username ?? '—' }}) · Telegram ID: {{ $ticket->participant->telegram_id }}
    </p>
    <p style="color:var(--muted);font-size:13px">Создано: {{ $ticket->created_at->format('d.m.Y H:i') }}
        @if($ticket->first_response_at) · Первый ответ: {{ $ticket->first_response_at->format('d.m.Y H:i') }}@endif
    </p>
</div>

<div class="card">
    <h3 style="margin-bottom:12px">История переписки</h3>
    @foreach($history as $msg)
    <div class="msg {{ $msg->role }}">
        <div class="meta">{{ $msg->role }} · {{ $msg->created_at->format('d.m H:i') }}</div>
        <div style="white-space:pre-wrap">{{ $msg->content }}</div>
    </div>
    @endforeach
</div>

@if($ticket->status === 'open')
<div class="card">
    <h3 style="margin-bottom:12px">Ответ участнику</h3>
    <form method="POST" action="{{ route('operator.tickets.reply', $ticket) }}">
        @csrf
        <textarea name="message" required placeholder="Текст ответа..."></textarea>
        <button type="submit" class="btn btn-primary" style="margin-top:12px">Отправить в Telegram</button>
    </form>
</div>
@endif
@endsection
