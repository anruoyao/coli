<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'これらの認証情報は当社の記録と一致しません。',

    // Guest auth gate
    'gate_title' => '続行するにはログインしてください',
    'gate_caption' => 'ログインして、いいね、コメント、フォローなどコミュニティでの交流をお楽しみください。',
    'gate_signup' => 'アカウントを作成',
    'gate_continue' => '閲覧を続ける',
    'gate_back' => '戻る',
    'gate_no_account' => 'アカウントをお持ちでないですか？',
    'gate_signup_link' => '新規登録',
    'email_blocked' => 'お使いのメールアドレスはブロックされているため、このプラットフォームでアカウントを作成できません。',
    'ip_blocked' => 'お使いの IP アドレスはブロックされているため、このプラットフォームにアクセスできません。',
    'email_not_found' => 'このメールアドレスのアカウントが見つかりませんでした。アドレスを確認して、もう一度お試しください。',
    'password' => '入力されたパスワードが正しくありません。',
    'birthdate_required' => '生年月日を選択してください',
    'password_label' => 'パスワード',
    'password_strength_helper' => ":min_length 文字以上のパスワードを入力してください。大文字と小文字、数字、記号（例: {'@'}\$!%*?&）を含める必要があります。",
    'throttle' => 'ログイン試行回数が多すぎます。:seconds 秒後にもう一度お試しください。',
    'restore_access' => 'アクセスを復元',
    'back_to_login' => 'ログインページに戻る',
    'new_password' => '新しいパスワード',
    'enter_new_password' => '新しいパスワードを入力',
    'new_password_helper' => 'アカウントの新しいパスワードを入力してください。',
    'restore_access_helper' => ':app_name で使用しているメールアドレスを入力してください。',
    'linked_account_error' => 'このアカウントはすでに別の :app_name アカウントにリンクされています。',
    'already_linked_account_error' => 'このアカウントはすでに現在の :app_name アカウントにリンクされています。',
    'master_account_error' => '他のアカウントをリンクするには、:app_name のマスターアカウントを使用してください。',
    'registration_disabled' => '現在、新規登録は無効になっています。しばらくしてからもう一度お試しください。',
    'registration_conflict' => 'このメールアドレスまたはユーザー名は、他のユーザーによって登録されたばかりです。もう一度お試しになるか、別のものをお使いください。',
    'login_disabled' => '現在、ログインは無効になっています。しばらくしてからもう一度お試しください。',
    'email_send_failed' => 'メールの送信に失敗しました。しばらくしてからもう一度お試しになるか、メール設定を確認してください。',
    'invalid_reset_token' => 'リセットリンクが無効または期限切れです。新しいリンクをリクエストしてください。',
    'verification_disabled' => '現在、メール認証は無効になっています。',
    'verification_code_required' => 'メールに送信された6桁の認証コードを入力してください。',
    'verification_code_not_found' => '有効な認証コードが見つかりません。新しいコードをリクエストしてください。',
    'verification_code_expired' => '認証コードの有効期限が切れました。新しいコードをリクエストしてください。',
    'verification_code_invalid' => '認証コードが正しくありません。確認してもう一度お試しください。',
    'verification_code_too_many_attempts' => '誤った入力回数が多すぎます。新しい認証コードをリクエストしてください。',
    'verification_code_resent_wait' => '新しいコードをリクエストする前に :seconds 秒お待ちください。',
    'login_to_cp' => [
        'title' => '管理パネル',
        'caption' => '管理者のログイン情報とパスワードを入力してください。',
    ],
    'login_to_app' => [
        'title' => ':app_name にログイン',
        'caption' => 'またお会いできてうれしいです！',
    ],
    'signup_for_app' => [
        'title' => ':app_name に登録',
        'caption' => '簡単でスピーディーに参加できます',
    ],
    'linker_login' => [
        'title' => 'アカウントをリンク',
        'caption' => '既存の :app_name アカウントをこのアカウントにリンクします。',
        'button' => 'アカウントをリンク',
    ],
    'login_with' => ':provider_name でサインイン',
    'or_continue' => 'または次で続行',
    'email_continue' => 'メールアドレスで続行',
    'forgot_password' => 'パスワードを忘れた場合',
    'resend_link' => 'リンクを再送信',
    'resend_link_helper' => 'メールが届いていませんか？迷惑メールフォルダやプロモーションフォルダを確認するか、再度送信してください:',
    'resend_link_error' => '再送信は30分ごとに可能です。前回の試行から30分経過するまでお待ちください',
    'resend_link_success' => 'リンクを再送信しました。メールが迷惑メールフォルダに届く場合があるため、受信トレイまたは迷惑メールフォルダを確認してください',
    'forgot_success_message' => [
        'title' => 'メールを送信しました！',
        'caption' => 'アカウントを復元するには、メール内のリンクに従ってください: :email_address。',
    ],
    'signup_success_message' => [
        'title' => 'もう少しです！',
        'caption' => 'メールアドレス :email_address に、専用のログインリンクを送信しました。',
    ],
    'login_or_email' => 'ログイン ID またはメールアドレス',
    'enter_email' => 'メールアドレスを入力',
    'email' => 'メールアドレス',
    'signup' => '新規登録',
    'create_account' => 'アカウントを作成',
    'signin' => 'サインイン',
    'hi_there' => 'こんにちは！👋',
    'remember_me' => 'ログイン状態を保持',
    'other_options' => 'その他のオプション',
    'enter_password' => 'パスワードを入力',
    'already_have_account' => 'すでにアカウントをお持ちですか？',
    'auth_agreement' => '続行することで、:app_name の主要ドキュメントである<a href=":terms_link" target="_blank" class="underline hover:text-brand-900">利用規約</a>および<a href=":policy_link" target="_blank" class="underline hover:text-brand-900">プライバシーポリシー</a>に同意したものとみなされます',
];
