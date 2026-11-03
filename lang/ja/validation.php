<?php

return [
    'required' => ':attribute は必須です。',
    'required_if' => ':other が :value の場合、:attribute は必須です。',
    'string' => ':attribute は文字列を指定してください。',
    'email' => ':attribute には有効なメールアドレスを指定してください。',
    'date' => ':attribute には有効な日付を指定してください。',
    'after' => ':attribute には :date より後の日付を指定してください。',
    'enum' => '選択された :attribute は無効です。',

    'max' => [
        'string' => ':attribute は :max 文字以下で入力してください。',
    ],
];
