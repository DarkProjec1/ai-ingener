@extends('operator.layout')
@section('title', 'Вход')
@section('content')
<div class="card" style="max-width:400px;margin:80px auto">
    <h2 style="margin-bottom:16px">Вход для операторов</h2>
    @if($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <label style="color:var(--muted);font-size:13px">Email</label>
        <input type="email" name="email" value="{{ old('email', 'operator@example.com') }}" required autofocus>
        <label style="color:var(--muted);font-size:13px">Пароль</label>
        <input type="password" name="password" value="password" required>
        <button type="submit" class="btn btn-primary" style="width:100%">Войти</button>
    </form>
    <p style="margin-top:16px;color:var(--muted);font-size:13px">Демо: operator@example.com / password</p>
</div>
@endsection
