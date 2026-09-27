@extends('emails.layouts.main')

@section('email_content')
    @php
        $zh = str_starts_with($locale, 'zh');
        $settingsUrl = url('/settings/email-notifications');

        // 标题字号/字重映射（与后台配置的 title_size / title_weight 对应）
        $titleSizeMap = ['sm' => '15px', 'md' => '18px', 'lg' => '22px'];
        $titleWeightMap = ['normal' => '400', 'medium' => '500', 'semibold' => '600', 'bold' => '700'];
        $titleSize = $titleSizeMap[$style['title_size'] ?? 'md'] ?? '18px';
        $titleWeight = $titleWeightMap[$style['title_weight'] ?? 'semibold'] ?? '600';
    @endphp

    {{-- Banner 图 --}}
    @if(!empty($imageUrl))
        <div style="margin: 0 0 16px 0;">
            <a href="{{ $destinationUrl ?: url('/') }}" style="text-decoration: none;">
                <img src="{{ $imageUrl }}" alt=""
                     style="display: block; width: 100%; max-width: 340px; height: auto; border-radius: 8px; margin: 0 auto;">
            </a>
        </div>
    @endif

    {{-- 标题（字号/字重可配置） --}}
    <h2 style="font-size: {{ $titleSize }}; font-weight: {{ $titleWeight }}; line-height: 1.3; padding: 0; margin: 0 0 12px 0; color: #333333;">
        {{ $campaignTitle }}
    </h2>

    {{-- 正文 --}}
    <p style="font-size: 13px; font-weight: normal; padding: 0; margin: 0; color: #555555; line-height: 1.6;">
        {!! nl2br(e($campaignContent)) !!}
    </p>

    {{-- 站内帖子卡片（最多 3 个，垂直堆叠） --}}
    @if(!empty($posts))
        <div style="height: 22px; line-height: 22px; font-size: 1px;">&nbsp;</div>
        <div style="font-size: 11px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #999999; margin: 0 0 10px 0;">
            {{ $zh ? '站内帖子' : 'FROM THE COMMUNITY' }}
        </div>

        @foreach($posts as $post)
            <div style="margin: 0 0 10px 0;">
                <a href="{{ $post['url'] }}"
                   style="display: block; text-decoration: none; color: inherit; background-color: #f7f7f7; border-radius: 8px; overflow: hidden;">

                    {{-- 帖子封面 --}}
                    @if(!empty($post['cover_url']))
                        <img src="{{ $post['cover_url'] }}" alt=""
                             style="display: block; width: 100%; max-width: 340px; height: auto; border: 0;">
                    @endif

                    <div style="padding: 12px 14px;">
                        {{-- 摘要 --}}
                        <div style="font-size: 13px; font-weight: 600; color: #333333; line-height: 1.45; margin: 0 0 8px 0;">
                            {{ $post['excerpt'] }}
                        </div>

                        {{-- 作者行 --}}
                        <table cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse;">
                            <tr>
                                @if(!empty($post['author_avatar']))
                                    <td style="padding: 0 6px 0 0; vertical-align: middle;">
                                        <img src="{{ $post['author_avatar'] }}" alt="" width="20" height="20"
                                             style="display: block; width: 20px; height: 20px; border-radius: 10px; border: 0;">
                                    </td>
                                @endif
                                <td style="vertical-align: middle;">
                                    <span style="font-size: 12px; color: #777777;">
                                        {{ $post['author_name'] ?: ($zh ? '社区成员' : 'Community member') }}
                                    </span>
                                </td>
                            </tr>
                        </table>

                        {{-- 统计行 --}}
                        <div style="font-size: 11px; color: #999999; margin: 6px 0 0 0;">
                            {{ number_format($post['reactions_count']) }} {{ $zh ? '个赞同' : 'reactions' }}
                            &nbsp;·&nbsp;
                            {{ number_format($post['comments_count']) }} {{ $zh ? '条评论' : 'comments' }}
                        </div>

                        {{-- 阅读全文 --}}
                        <div style="font-size: 12px; font-weight: 600; color: #333333; margin: 10px 0 0 0;">
                            {{ $zh ? '阅读全文' : 'Read full post' }} &rarr;
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    @endif

    {{-- CTA 按钮 --}}
    @if($destinationUrl)
        <div style="height: 24px; line-height: 24px; font-size: 1px;">&nbsp;</div>
        <div style="text-align: center;">
            <a href="{{ $destinationUrl }}"
               style="background-color: #333333; text-align: center; padding: 8px 25px; margin: 0; border-radius: 6px; text-decoration: none; color: #f3f3f3; font-size: 13px; font-weight: 400; display: inline-block;">
                {{ $zh ? '查看详情' : 'View details' }}
            </a>
        </div>
    @endif

    <div style="height: 24px; line-height: 24px; font-size: 1px;">&nbsp;</div>
    <p style="font-size: 13px; padding: 0; margin: 0; color: #555555;">
        {{ $zh ? '如果不想再收到此类邮件，可在通知设置的「平台通知」中关闭。' : "If you no longer wish to receive these emails, you can turn off \"Platform notifications\" in your notification settings." }}
        <br>
        <a href="{{ $settingsUrl }}" style="color: #333333; text-decoration: underline; font-size: 12px;">
            {{ $zh ? '前往通知设置' : 'Go to notification settings' }}
        </a>
    </p>
@endsection
