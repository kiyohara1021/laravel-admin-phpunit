@extends('layouts.admin')

@section('title', 'お知らせの作成')

@section('content')
    @if ($errors->any())
        <div class="errors">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form class="panel" style="padding: 24px;" method="POST" action="{{ route('admin.notices.store') }}">
        @csrf
        @include('admin.notices._form')
        <button class="btn" type="submit">作成する</button>
        <a class="btn secondary" href="{{ route('admin.notices.index') }}">戻る</a>
    </form>
@endsection
