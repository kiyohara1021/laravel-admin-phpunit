<?php

namespace App\Http\Requests;

use App\Enums\NoticeStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNoticeRequest extends FormRequest
{
    /**
     * 認可は App\Policies\NoticePolicy に集約している。
     * 区画の入場は admin ミドルウェア、操作の可否はコントローラの Gate::authorize が見る。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 予約公開以外では日時欄が空で送られてくるため、null に寄せておく。
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('published_at') === '') {
            $this->merge(['published_at' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(NoticeStatus::class)],
            'published_at' => [
                'nullable',
                'required_if:status,'.NoticeStatus::Scheduled->value,
                'date',
                'after:now',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'body' => '本文',
            'status' => '状態',
            'published_at' => '公開日時',
        ];
    }
}
