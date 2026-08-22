<?php

return [
    'search_placeholder' => '搜索',
    'search_button' => '搜索',
    'clear_search' => '清除搜索',
    'filters' => [
        'users' => [
            'tabs' => [
                'all' => '全部类型',
                'authors' => '创作者',
                'readers' => '读者',
            ],
            'description' => '按用户名、邮箱、姓名、ID、IP 地址或描述搜索用户。您也可以按类别（如创作者或读者）浏览用户。',
        ],
        'categories' => [
            'tabs' => [
                'all' => '全部类型',
                'product' => '商品',
                'job' => '职位',
            ]
        ],
        'market' => [
            'tabs' => [
                'all' => '全部状态',
                'approved' => '已通过',
                'rejected' => '已拒绝',
                'pending' => '待审核',
            ],
            'description' => '使用搜索按标题或描述查找商品，或按审核状态浏览。',
        ],
        'jobs' => [
            'tabs' => [
                'all' => '全部状态',
                'approved' => '已通过',
                'rejected' => '已拒绝',
                'pending' => '待审核',
            ],
            'description' => '使用搜索按标题或描述查找职位，或按审核状态浏览。',
        ],
        'ads' => [
            'tabs' => [
                'all' => '全部状态',
                'approved' => '已通过',
                'rejected' => '已拒绝',
                'pending' => '待审核',
            ],
            'description' => '使用搜索按标题或描述查找广告，或按审核状态浏览。',
        ],
        'chats' => [
            'tabs' => [
                'all' => '全部类型',
                'direct' => '私聊',
                'group' => '群聊',
            ],
            'description' => '使用搜索按参与者姓名、群组标题或描述、ID 或聊天哈希 ID 查找聊天，或按类型（私聊或群聊）浏览。',
        ],
        'cashouts' => [
            'tabs' => [
                'all' => '全部申请',
                'paid' => '已支付',
                'rejected' => '已拒绝',
                'pending' => '待审核',
                'cancelled' => '已取消',
            ],
            'description' => '使用搜索按用户名、邮箱或申请编号查找提现申请。',
        ],
    ]
];
