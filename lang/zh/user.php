<?php

return [
    'validation' => [
        'username' => [
            'required' => '用户名不能为空。',
            'unique' => '此用户名已被占用。',
            'regex' => '用户名只能包含字母、数字、下划线和点号。',
            'min' => '用户名太短，至少需要 :min 个字符。',
            'max' => '用户名太长，不能超过 :max 个字符。'
        ],
        'first_name' => [
            'required' => '姓名字段为必填项。',
            'min' => '姓名至少需要 :min 个字符。',
            'max' => '姓名不能超过 :max 个字符。'
        ],
        'last_name' => [
            'min' => '姓氏至少需要 :min 个字符。',
            'max' => '姓氏不能超过 :max 个字符。'
        ],
        'bio' => [
            'min' => '个人简介至少需要 :min 个字符。',
            'max' => '个人简介不能超过 :max 个字符。'
        ],
        'gender' => [
            'in' => '性别必须是"男"或"女"。'
        ],
        'website' => [
            'url' => '网站必须是有效的 URL。',
            'max' => '网站不能超过 :max 个字符。'
        ],
        'email' => [
            'required' => '邮箱字段不能为空。',
            'email' => '邮箱地址无效。',
            'max' => '邮箱地址不能超过 :max 个字符。',
            'unique' => '此邮箱地址已被占用。'
        ],
        'phone' => [
            'required' => '电话号码字段不能为空。',
            'regex' => '电话号码无效。',
            'max' => '电话号码不能超过 :max 个字符。',
            'string' => '电话号码无效。'
        ],
        'password' => [
            'required' => '密码字段不能为空。',
            'string' => '密码格式无效。',
            'min' => '密码至少需要 :min 个字符。',
            'max' => '密码不能超过 :max 个字符。',
            'incorrect' => '密码不正确。',
            'mixed_case' => '密码必须同时包含大写和小写字母。',
            'letters' => '密码必须至少包含一个字母。',
            'numbers' => '密码必须至少包含一个数字。',
            'symbols' => '密码必须至少包含一个特殊字符。'
        ],
        'caption' => [
            'min' => '描述至少需要 :min 个字符。',
            'max' => '描述不能超过 :max 个字符。'
        ]
    ],
    'account_deleted' => '您的账户已成功删除。希望很快再见到您。',
];
