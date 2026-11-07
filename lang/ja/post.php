<?php

return [
    'pin_limit' => 'プロフィールに固定できる投稿は最大 :limit 件です。先に固定を解除してください。',
    'pin_inactive' => '公開済みの投稿のみ固定できます。',
    'validation' => [
        'add_post_text' => 'あなたの思いを共有するために、テキストを入力してください。',
        'add_poll_text' => 'すべての投票には質問が必要です。質問を入力してください。',
        'image_limit_reach' => 'この投稿に追加できる画像は :max_images_count 枚までです。',
        'poll' => [
            'options_required' => '投票には少なくとも2つの選択肢を入力してください。',
            'invalid_data' => '投票データが無効です。',
            'min_options' => '投票には少なくとも :min 個の選択肢が必要です。',
            'max_options' => '投票の選択肢は :max 個を超えることはできません。',
            'option_text' => '各投票の選択肢にはテキストが必要です。',
            'option_text_min' => '投票の選択肢のテキストは少なくとも :min 文字必要です。',
            'option_text_max' => '投票の選択肢のテキストは :max 文字を超えることはできません。'
        ],
        'wrong_type_attachment' => ':file_type ファイルをこの種類の投稿に添付することはできません。',
    ]
];
