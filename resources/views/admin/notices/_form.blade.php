@php
    $notice = $notice ?? null;
    $published = old('published', $notice?->published_at !== null);
@endphp

<label for="title">タイトル</label>
<input id="title" type="text" name="title" value="{{ old('title', $notice->title ?? '') }}" required maxlength="100">

<label for="body">本文</label>
<textarea id="body" name="body" required maxlength="5000">{{ old('body', $notice->body ?? '') }}</textarea>

<label class="checkbox">
    <input type="checkbox" name="published" value="1" @checked($published)>
    公開する
</label>
