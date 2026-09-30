@extends('operator.layout')
@section('title', 'Обращения')
@section('content')
<h1 style="margin-bottom:16px">Обращения</h1>
<div class="tabs" style="margin-bottom:16px">
    <a href="?status=open" class="{{ $status==='open'?'active':'' }}">Открытые</a>
    <a href="?status=closed" class="{{ $status==='closed'?'active':'' }}">Закрытые</a>
    <a href="?status=all" class="{{ $status==='all'?'active':'' }}">Все</a>
</div>
<div class="card">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Участник</th>
                <th>Тема / последнее</th>
                <th>Статус</th>
                <th>Создано</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($tickets as $t)
            <tr>
                <td>{{ $t->id }}</td>
                <td>
                    {{ $t->participant->first_name ?? '—' }}
                    <div style="color:var(--muted);font-size:12px">tg:{{ $t->participant->telegram_id }}</div>
                </td>
                <td style="max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $t->last_user_message ?? $t->subject }}</td>
                <td><span class="badge {{ $t->status }}">{{ $t->status }}</span></td>
                <td>{{ $t->created_at->format('d.m.Y H:i') }}</td>
                <td><a href="{{ route('operator.tickets.show', $t) }}" class="btn btn-ghost">Открыть</a></td>
            </tr>
            @empty
            <tr><td colspan="6" style="color:var(--muted)">Нет обращений</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:16px">{{ $tickets->withQueryString()->links() }}</div>
</div>
@endsection
