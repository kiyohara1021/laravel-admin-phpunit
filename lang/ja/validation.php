<?php

// このアプリで使うルールだけを訳している。ほかのルールは fallback_locale（en）の文言になる
return [
    'required' => ':attributeは必須です。',
    'required_if' => ':otherが:valueの場合、:attributeは必須です。',
    'string' => ':attributeは文字列を指定してください。',
    'email' => ':attributeには有効なメールアドレスを指定してください。',
    'date' => ':attributeには有効な日付を指定してください。',
    'after' => ':attributeには:dateより後の日付を指定してください。',
    'enum' => '選択された:attributeは無効です。',

    'max' => [
        'string' => ':attributeは:max文字以下で入力してください。',
    ],

    // required_if の :value に差し込まれる値の表示名
    'values' => [
        'status' => [
            'scheduled' => '予約公開',
        ],
    ],
];
