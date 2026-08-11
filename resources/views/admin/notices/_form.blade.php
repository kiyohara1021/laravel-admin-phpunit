@php
    $notice = $notice ?? null;
    $statuses = \App\Enums\NoticeStatus::cases();
    $status = old('status', $notice?->status()->value ?? \App\Enums\NoticeStatus::Draft->value);
    $publishedAt = old('published_at', $notice?->isScheduled() ? $notice->published_at->format('Y-m-d\TH:i') : null);
@endphp

<label for="title">タイトル</label>
<input id="title" type="text" name="title" value="{{ old('title', $notice->title ?? '') }}" required maxlength="100">

<label for="body">本文</label>
<textarea id="body" name="body" required maxlength="5000">{{ old('body', $notice->body ?? '') }}</textarea>

<fieldset class="statuses">
    <legend>状態</legend>
    @foreach ($statuses as $case)
        <label class="checkbox">
            <input type="radio" name="status" value="{{ $case->value }}" @checked($status === $case->value)>
            {{ $case->label() }}
        </label>
    @endforeach
</fieldset>

<label for="published_at">公開日時</label>
<input id="published_at" type="datetime-local" name="published_at" value="{{ $publishedAt }}">
<p class="muted field-note">「予約公開」を選んだときだけ使います。未来の日時を指定してください。</p>
