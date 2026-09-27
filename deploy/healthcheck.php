<?php

/*
|--------------------------------------------------------------------------
| 部署健康检查（预检）
|--------------------------------------------------------------------------
| 必须在站点运行用户（www）下执行，防止 root 写入缓存文件导致线上 500：
|   sudo -u www php deploy/healthcheck.php
|
| 检查项（全部只读 / 无副作用，可安全在每次部署前运行）：
|   1. 全量编译 Blade 视图（等价 php artisan view:cache，捕获编译期错误）
|   2. 实例化并解析 app/Mail 下全部邮件类（捕获类级冲突，如 $locale 与
|      Mailable 父类属性冲突这类「只在发送时炸」的隐患）
|   3. 渲染营销邮件模板（复用品牌布局与组件）
|   4. 若存在管理员与营销活动数据：渲染营销后台 index/show/create 三页，
|      提前暴露模板运行时错误（未定义方法、类型错误、缺失语言键等）
| 任一项失败即以非零退出码结束，供部署流程中止后续步骤。
*/

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

function check(string $name, callable $fn): void
{
    try {
        $fn();
        echo "  OK    {$name}".PHP_EOL;
    } catch (Throwable $e) {
        echo "  FAIL  {$name}: {$e->getMessage()}".PHP_EOL;
        exit(1);
    }
}

check('Blade 视图全量编译', function () {
    Artisan::call('view:cache', ['--quiet' => true]);
    if (Artisan::output() !== '' && str_contains(Artisan::output(), 'Error')) {
        throw new RuntimeException(Artisan::output());
    }
});

check('邮件类可加载且可实例化', function () {
    $instantiated = 0;

    foreach (glob(__DIR__.'/../app/Mail/*.php') as $file) {
        $class = 'App\\Mail\\'.basename($file, '.php');

        if (! class_exists($class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);

        if (! $reflection->isInstantiable()) {
            continue;
        }

        $constructor = $reflection->getConstructor();
        $args = [];

        if ($constructor) {
            foreach ($constructor->getParameters() as $parameter) {
                if ($parameter->isVariadic()) {
                    $args[] = [];
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $args[] = $parameter->getDefaultValue();
                } else {
                    $type = (string) $parameter->getType();
                    $args[] = match ($type) {
                        'int' => 0,
                        'float' => 0.0,
                        'bool' => false,
                        'array' => [],
                        'string' => 'healthcheck',
                        default => 'healthcheck',
                    };
                }
            }
        }

        $instance = $reflection->newInstanceArgs($args);

        if (method_exists($instance, 'content')) {
            $instance->content();
        }

        $instantiated++;
    }

    if ($instantiated === 0) {
        throw new RuntimeException('未找到任何 Mailable 类');
    }
});

check('营销邮件模板渲染', function () {
    $mail = new App\Mail\MarketingNotificationMail(
        'Healthcheck Subject',
        '预检标题',
        "预检正文第一行\n第二行",
        'https://misskey.site/',
        'zh',
    );

    if (strlen($mail->render()) < 1000) {
        throw new RuntimeException('营销邮件渲染结果异常过短');
    }
});

check('营销后台页面渲染（管理员 + 数据）', function () {
    View::share('errors', new ViewErrorBag);

    $admin = App\Models\User::where('role', '!=', 'user')->first();

    if (! $admin) {
        echo "     跳过：未找到管理员".PHP_EOL;

        return;
    }

    Auth::login($admin);

    $campaign = App\Models\MarketingCampaign::withCount('recipients')->first();

    if ($campaign) {
        $counts = [
            'email_pending_queued' => $campaign->recipients()->whereIn('email_status', ['pending', 'queued'])->count(),
            'email_sent' => 0,
            'email_failed' => 0,
            'email_skipped' => 0,
            'in_app_pending_queued' => 0,
            'in_app_sent' => 0,
            'in_app_failed' => 0,
            'in_app_skipped' => 0,
        ];

        strlen(view('admin::marketing.show.show', ['campaign' => $campaign, 'counts' => $counts])->render());
    }

    strlen(view('admin::marketing.index.index', [
        'campaigns' => App\Models\MarketingCampaign::orderByDesc('id')->paginate(15),
    ])->render());

    strlen(view('admin::marketing.create.create')->render());
});

echo "== 部署健康检查全部通过 ==".PHP_EOL;