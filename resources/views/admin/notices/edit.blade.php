@extends('layouts.admin')

@section('title', 'お知らせの編集')

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

    <form class="panel" style="padding: 24px;" method="POST" action="{{ route('admin.notices.update', $notice) }}">
        @csrf
        @method('PUT')
        @include('admin.notices._form', ['notice' => $notice])
        <button class="btn" type="submit">更新する</button>
        <a class="btn secondary" href="{{ route('admin.notices.index') }}">戻る</a>
    </form>
@endsection
