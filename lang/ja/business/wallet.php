<?php

return [
	'index_title' => 'ウォレット',
	'cashouts_title' => '出金履歴',
    'withdrawal_title' => '出金リクエスト',
	'balance_desc' => '利用可能残高',
	'open_wallet_btn' => 'ウォレットを開く',
	'wallet_number' => 'ウォレット番号',
	'request_withdrawal' => '出金をリクエスト',
	'about_wallet_text' => ':wallet_name ウォレットはプラットフォーム内でのみ使用できます。資金はここでのサービスの支払いに使用するもので、外部への送金はできません。<a href=":about_link" target="_blank" class="text-brand-900 hover:underline">詳しくはこちら。</a>',
    'form' => [
        'amount' => '金額',
        'amount_placeholder' => '金額を入力',
        'amount_helper' => '出金したい金額を入力してください。',
        'payment_method' => '支払い方法',
        'payment_method_placeholder' => '方法を選択',
        'credentials' => '認証情報',
        'credentials_placeholder' => '認証情報を入力',
        'credentials_helper' => '選択した支払い方法のアカウント詳細（ウォレットアドレス、銀行口座番号、その他必要な情報など）を入力してください。',
        'reviewer_notes' => 'レビュアー注記（任意）',
        'reviewer_notes_placeholder' => '注記を入力（任意）',
        'reviewer_notes_helper' => 'レビュアーに役立つ追加情報があれば入力してください。これはレビュアーのみに表示されます。',
    ],
    'validation' => [
        'amount' => [
            'insufficient_balance' => '利用可能残高を超える金額が入力されました。',
            'pending_request' => '保留中のリクエストがあります。処理が完了するまでお待ちいただき、その後新しいリクエストを行ってください。',
        ],
    ],
];
