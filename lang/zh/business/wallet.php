<?php

return [
	'index_title' => '钱包',
	'cashouts_title' => '提现记录',
    'withdrawal_title' => '申请提现',
	'balance_desc' => '可用余额',
	'open_wallet_btn' => '打开钱包',
	'wallet_number' => '钱包编号',
	'request_withdrawal' => '申请提现',
	'about_wallet_text' => ':wallet_name 钱包仅限在平台内使用。资金仅用于支付平台内的服务，无法转出至外部<a href=":about_link" target="_blank" class="text-brand-900 hover:underline">了解更多</a>。',
    'form' => [
        'amount' => '金额',
        'amount_placeholder' => '请输入金额',
        'amount_helper' => '输入您想要提现的金额。',
        'payment_method' => '支付方式',
        'payment_method_placeholder' => '选择方式',
        'credentials' => '凭证信息',
        'credentials_placeholder' => '请输入凭证信息',
        'credentials_helper' => '提供您所选支付方式的账户详情（例如：钱包地址、银行账号及其他所需信息）。',
        'reviewer_notes' => '审核备注（可选）',
        'reviewer_notes_placeholder' => '请输入备注（可选）',
        'reviewer_notes_helper' => '提供任何可能对审核人员有帮助的附加信息。此信息仅审核人员可见。',
    ],
    'validation' => [
        'amount' => [
            'insufficient_balance' => '您输入的金额超过了您的可用余额。',
            'pending_request' => '您有一个待处理的请求。请等待处理完成后再提交新请求。',
        ],
    ],
];