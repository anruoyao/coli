<?php

return [
    'validation' => [
        'add_post_text' => '请添加一些文字来分享您的想法。',
        'add_poll_text' => '每个投票都需要一个问题，请添加一个。',
        'image_limit_reach' => '您最多只能为此帖子添加 :max_images_count 张图片。',
        'poll' => [
            'options_required' => '请提供至少两个投票选项。',
            'invalid_data' => '投票数据无效。',
            'min_options' => '投票至少需要有 :min 个选项。',
            'max_options' => '投票不能有超过 :max 个选项。',
            'option_text' => '每个投票选项必须有文字。',
            'option_text_min' => '投票选项文字至少需要 :min 个字符。',
            'option_text_max' => '投票选项文字不能超过 :max 个字符。'
        ],
        'wrong_type_attachment' => '无法将 :file_type 文件附加到此类型帖子。',
    ]
];
