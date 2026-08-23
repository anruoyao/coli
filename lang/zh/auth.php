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

    'failed' => '账号或密码错误，请重新输入。',
    'email_blocked' => '您的电子邮箱已被封禁，无法在本平台注册账号。',
    'ip_blocked' => '您的 IP 地址已被封禁，无法访问本平台。',
    'email_not_found' => '我们找不到使用该邮箱的账号，请检查邮箱地址后重试。',
    'password' => '密码不正确。',
    'birthdate_required' => '请选择您的出生日期',
    'password_label' => '密码',
    'password_strength_helper' => '请输入至少 :min_length 个字符的密码，需包含大小写字母、数字和特殊符号（如 @$!%*?&）。',
    'throttle' => '登录尝试次数过多，请在 :seconds 秒后重试。',
    'restore_access' => '恢复访问',
    'back_to_login' => '返回登录页面',
    'new_password' => '新密码',
    'enter_new_password' => '输入新密码',
    'new_password_helper' => '为您的账号输入一个新密码。',
    'restore_access_helper' => '输入您在 :app_name 使用的电子邮箱地址。',
    'linked_account_error' => '此账号已关联到另一个 :app_name 账号。',
    'already_linked_account_error' => '此账号已关联到您当前的 :app_name 账号。',
    'master_account_error' => '请使用您的 :app_name 主账号来关联其他账号。',
    'registration_disabled' => '注册功能当前已禁用，请稍后再试。',
    'login_disabled' => '登录功能当前已禁用，请稍后再试。',
    'email_send_failed' => '邮件发送失败，请稍后重试或检查邮箱设置。',
    'invalid_reset_token' => '重置链接无效或已过期，请重新发起找回密码。',
    'login_to_cp' => [
        'title' => '管理后台',
        'caption' => '输入您的管理员账号和密码。',
    ],
    'login_to_app' => [
        'title' => '登录 :app_name',
        'caption' => '很高兴再次见到您！',
    ],
    'signup_for_app' => [
        'title' => '注册 :app_name',
        'caption' => '加入我们，简单快捷',
    ],
    'linker_login' => [
        'title' => '关联账号',
        'caption' => '将您已有的 :app_name 账号与此账号关联。',
        'button' => '关联账号',
    ],
    'login_with' => '使用 :provider_name 登录',
    'or_continue' => '或继续使用',
    'email_continue' => '使用邮箱继续',
    'forgot_password' => '忘记密码',
    'resend_link' => '重新发送链接',
    'resend_link_helper' => '没有收到邮件？请检查垃圾邮件或推广邮件文件夹，或点击重新发送：',
    'resend_link_error' => '每 30 分钟只能重新发送一次，请在上次发送后等待 30 分钟',
    'resend_link_success' => '链接已重新发送，请查收收件箱或垃圾邮件文件夹，邮件有时可能会进入该文件夹',
    'forgot_success_message' => [
        'title' => '邮件已发送！',
        'caption' => '要恢复您的账号，请点击发送至 :email_address 的邮件中的链接。',
    ],
    'signup_success_message' => [
        'title' => '即将完成！',
        'caption' => '我们刚刚向您的邮箱 :email_address 发送了一个专属登录链接。',
    ],
    'login_or_email' => '用户名或邮箱',
    'enter_email' => '输入您的邮箱',
    'email' => '邮箱',
    'signup' => '注册',
    'create_account' => '创建账号',
    'signin' => '登录',
    'hi_there' => '嗨，你好！👋',
    'remember_me' => '记住我',
    'other_options' => '其他选项',
    'enter_password' => '输入密码',
    'already_have_account' => '已有账号？',
    'auth_agreement' => '继续即表示您同意 :app_name 的主要文件条款：<a href=":terms_link" target="_blank" class="underline hover:text-brand-900">服务条款</a> 和 <a href=":policy_link" target="_blank" class="underline hover:text-brand-900">隐私政策</a>',
];
