<?php

namespace App\Http\Controllers\Api\User\Feedback;

use Exception;
use App\Models\Post;
use App\Models\User;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use App\Enums\Report\ReportType;
use App\Http\Controllers\Controller;
use App\Services\Feedback\ReportService;
use App\Jobs\Feedback\SendReportNotificationEmailJob;
use App\Services\Feedback\ReportRateLimiter;
use App\Services\Feedback\ReportContentSanitizer;
use App\Traits\Http\Api\SupportsApiResponses;

class ReportController extends Controller
{
    use SupportsApiResponses;

    public function getReportReasons(Request $request)
    {
        $request->validate([
            'type' => ['required', 'string', Rule::in(ReportType::values())]
        ]);

        try {
            return $this->responseSuccess([
                'data' => $this->fetchReportReasons($request->type)
            ]);
        }

        catch(Exception $e) {
            return $this->responseError([
                'message' => $e->getMessage(),
                'errors' => [
                    'type' => [
                        $e->getMessage()
                    ]
                ]
            ]);
        }
    }

    public function sendReport(Request $request)
    {
        $request->validate([
            'type' => ['required', 'string', Rule::in(ReportType::values())],
            'reason_index' => ['required', 'integer'],
            'reportable_id' => ['required', 'integer'],
            'comment' => ['nullable', 'string', 'max:1000']
        ]);

        try {
            $reportableId = $request->input('reportable_id');
            $reportableType = $request->input('type');
            $reportReasonIndex = $request->input('reason_index');

            $reportReasonData = $this->fetchReportReasons($request->type);

            if(! isset($reportReasonData['reasons'][$reportReasonIndex])) {
                throw new Exception('Report reason index is invalid.');
            }

            // 举报限流（账号 + IP 双维度，持久化计数）
            $rateLimiter = app(ReportRateLimiter::class);

            $limitResult = $rateLimiter->check(me()->id, $request->ip());

            if($limitResult !== null) {
                return $this->responseError([
                    'message' => $this->buildRateLimitMessage($limitResult),
                    'data' => [
                        'limit' => $limitResult['limit'],
                        'window_hours' => $limitResult['window_hours'],
                        'retry_after' => $limitResult['retry_after'],
                        'next_available_at' => now()->addSeconds($limitResult['retry_after'])->toIso8601String()
                    ]
                ], Response::HTTP_TOO_MANY_REQUESTS)->withHeaders([
                    'Retry-After' => $limitResult['retry_after']
                ]);
            }

            // 补充说明净化：XSS 剥离 + 敏感词拦截（仅拒绝提交，不触发封禁）
            $sanitizer = app(ReportContentSanitizer::class);

            $comment = $sanitizer->sanitize($request->input('comment'));

            if($comment !== null && $sanitizer->containsBannedWords($comment)) {
                return $this->responseError([
                    'message' => __('api/toast.report_comment_banned_word'),
                    'errors' => [
                        'comment' => [
                            __('api/toast.report_comment_banned_word')
                        ]
                    ]
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $reportableData = $this->fetchReportableData($reportableId, $reportableType);

            // Delete all prev report, in case if user repeats this actions.

            $reportableData->reports()->where('reporter_id', me()->id)->delete();

            $report = $reportableData->reports()->create([
                'reporter_id' => me()->id,
                'reason_index' => $reportReasonIndex,
                'type' => ReportType::from($reportableType),
                'reporter_comment' => $comment,
                'ip_address' => $request->ip()
            ]);

            // 异步发送管理员邮件通知（队列处理，失败自动重试，不影响举报提交本身）
            SendReportNotificationEmailJob::dispatch($report->id);

            return $this->responseSuccess([
                'data' => [
                    'remaining' => $rateLimiter->remaining(me()->id)
                ]
            ]);
        }

        catch(Exception $e) {
            return $this->responseError([
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()]
            ]);
        }
    }

    private function fetchReportableData(int $reportableId, string $reportableType)
    {
        $reportableData = null;

        switch($reportableType) {
            case 'post':
                $reportableData = Post::activeById($reportableId)->excludeSelf()->first();
                break;
            case 'user':
                $reportableData = User::activeById($reportableId)->excludeSelf()->first();
                break;
            case 'group':
                $reportableData = Group::active()->where('id', $reportableId)->first();
                break;
        }

        if(! $reportableData) {
            throw new Exception('Reportable resource by given id not found.');
        }

        return $reportableData;
    }

    private function fetchReportReasons(string $type)
    {
        return (new ReportService($type))->getReasons();
    }

    /**
     * 限流提示文案：说明限流规则与下次可举报时间。
     */
    private function buildRateLimitMessage(array $limitResult): string
    {
        $nextAvailableAt = now()->addSeconds($limitResult['retry_after']);

        return __('api/toast.report_rate_limited', [
            'limit' => $limitResult['limit'],
            'window' => $limitResult['window_hours'],
            'time' => $nextAvailableAt->format('H:i')
        ]);
    }
}
