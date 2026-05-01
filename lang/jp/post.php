<?php

return [
    'validation' => [
        'add_post_text' => '考えを共有するためにいくつか言葉を追加してください。',
        'add_poll_text' => 'すべての投票には質問が必要です。追加してください。',
        'image_limit_reach' => 'この投稿に追加できる画像は :max_images_count 枚までです。',
        'poll' => [
            'options_required' => '投票のオプションを少なくとも2つ以上提供してください。',
            'invalid_data' => '投票データが無効です。',
            'min_options' => '投票は少なくとも :min つのオプションが必要です。',
            'max_options' => '投票は :max を超えるオプションを持てません。',
            'option_text' => '各投票オプションにはテキストが必要です。',
            'option_text_min' => '投票オプションのテキストは少なくとも :min 文字必要です。',
            'option_text_max' => '投票オプションのテキストは :max 文字を超えることはできません。'
        ],
        'wrong_type_attachment' => ':file_type ファイルをこの種類の投稿に添付できません。',
    ]
];
