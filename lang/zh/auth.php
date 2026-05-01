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

    'failed' => '这些凭证与我们的记录不匹配。',
    'email_blocked' => '您的邮箱地址已被屏蔽，无法用于在本平台创建账户。',
    'ip_blocked' => '您的 IP 地址已被屏蔽，无法访问本平台。',
    'email_not_found' => '我们找不到使用该邮箱的账户。请检查地址后重试。',
    'password' => '提供的密码不正确。',
    'birthdate_required' => '请选择您的出生日期',
    'password_label' => '密码',
    'password_confirmation_label' => '确认密码',
    'set_password' => '设置密码',
    'confirm_password' => '确认密码',
    'password_strength_helper' => '输入至少 :min 个字符的密码，包含大小写字母、数字和特殊符号（如 @$!%*?&）。',
    'throttle' => '登录尝试次数过多，请在 :seconds 秒后重试。',
    'restore_access' => '恢复访问',
    'back_to_login' => '返回登录页面',
    'new_password' => '新密码',
    'enter_new_password' => '输入新密码',
    'new_password_helper' => '为您的账户输入一个新密码。',
    'restore_access_helper' => '输入您用于 :app_name 的邮箱地址。',
    'linked_account_error' => '此账户已绑定到另一个 :app_name 账户。',
    'already_linked_account_error' => '此账户已绑定到您当前的 :app_name 账户。',
    'master_account_error' => '请使用您的 :app_name 主账户来绑定其他账户。',
    'login_to_app' => [
        'title' => '登录 :app_name',
        'caption' => '很高兴再次见到您！',
    ],
    'signup_for_app' => [
        'title' => '注册 :app_name',
        'caption' => '加入我们，简单快捷',
    ],
    'linker_login' => [
        'title' => '绑定账户',
        'caption' => '将您的 :app_name 现有账户与此账户绑定。',
        'button' => '绑定账户',
    ],
    'login_with' => '使用 :provider_name 登录',
    'or_continue' => '或继续使用',
    'email_continue' => '使用邮箱继续',
    'forgot_password' => '我忘记了密码',
    'resend_link' => '重新发送链接',
    'resend_link_helper' => '没有收到邮件？请检查您的垃圾邮件或促销文件夹，或重新发送：',
    'resend_link_error' => '您可以每 30 分钟重新发送一次。请在上次尝试后等待 30 分钟',
    'resend_link_success' => '链接已重新发送，请检查您的收件箱或垃圾邮件文件夹，邮件有时可能会被误分类',
    'forgot_success_message' => [
        'title' => '邮件已发送！',
        'caption' => '要恢复您的账户，请按照邮件中的链接操作：:email_address。',
    ],
    'signup_success_message' => [
        'title' => '即将完成！',
        'caption' => '我们刚刚向您的邮箱地址发送了一个唯一的登录链接：:email_address。',
    ],
    'login_or_email' => '用户名或邮箱',
    'enter_email' => '输入您的邮箱',
    'email' => '邮箱',
    'signup' => '注册',
    'create_account' => '创建账户',
    'signin' => '登录',
    'hi_there' => '你好！👋',
    'remember_me' => '记住我',
    'other_options' => '其他选项',
    'enter_password' => '输入密码',
    'already_have_account' => '已有账户？',
    'auth_agreement' => '继续即表示您同意 :app_name 的 <a href=":terms_link" target="_blank" class="underline hover:text-brand-900">服务条款</a> 和 <a href=":policy_link" target="_blank" class="underline hover:text-brand-900">隐私政策</a>',
];
