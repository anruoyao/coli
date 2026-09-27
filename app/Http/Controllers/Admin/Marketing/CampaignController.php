<?php

namespace App\Http\Controllers\Admin\Marketing;

use Throwable;
use App\Models\User;
use App\Support\Views\Flash;
use Illuminate\Http\Request;
use App\Models\MarketingCampaign;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Services\Marketing\CampaignService;

/**
 * 营销活动管理（后台）。
 *
 * 权限：整个 admin-area 已受 AdminRoleMiddleware 约束（仅 admin/root 可访问）；
 * 若启用 MARKETING_CAMPAIGN_SEND_ROOT_ONLY，则创建/发送/取消仅限 root 管理员。
 */
class CampaignController extends Controller
{
    protected CampaignService $campaignService;

    public function __construct(CampaignService $campaignService)
    {
        $this->campaignService = $campaignService;
    }

    public function index()
    {
        $campaigns = MarketingCampaign::query()
            ->withCount('recipients')
            ->latest('id')
            ->paginate(15);

        return view('admin::marketing.index.index', [
            'campaigns' => $campaigns,
        ]);
    }

    public function create()
    {
        $this->authorizeCampaignAccess();

        return view('admin::marketing.create.create');
    }

    public function store(Request $request)
    {
        $this->authorizeCampaignAccess();

        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:10000'],
            'landing_url' => ['nullable', 'url', 'max:255'],
            'email_enabled' => ['nullable', 'boolean'],
            'in_app_enabled' => ['nullable', 'boolean'],
            'target_type' => ['required', 'in:all,manual,type'],
            'target_user_ids' => ['nullable', 'string', 'max:10000'],
            'target_user_type' => ['nullable', 'in:author,reader'],
        ], attributes: [
            'title' => __('admin/marketing.form.title'),
            'subject' => __('admin/marketing.form.subject'),
            'content' => __('admin/marketing.form.content'),
            'landing_url' => __('admin/marketing.form.landing_url'),
            'email_enabled' => __('admin/marketing.form.email_enabled'),
            'in_app_enabled' => __('admin/marketing.form.in_app_enabled'),
            'target_type' => __('admin/marketing.form.target_type'),
            'target_user_ids' => __('admin/marketing.form.target_user_ids'),
            'target_user_type' => __('admin/marketing.form.target_user_type'),
        ]);

        $validator->after(function ($validator) use ($request) {
            if (! $request->boolean('email_enabled') && ! $request->boolean('in_app_enabled')) {
                $validator->errors()->add('email_enabled', __('admin/marketing.validation.channel_required'));
            }

            if ($request->input('target_type') === MarketingCampaign::TARGET_MANUAL && ! trim((string) $request->input('target_user_ids'))) {
                $validator->errors()->add('target_user_ids', __('admin/marketing.validation.target_user_ids_required'));
            }

            if ($request->input('target_type') === MarketingCampaign::TARGET_TYPE && ! $request->input('target_user_type')) {
                $validator->errors()->add('target_user_type', __('admin/marketing.validation.target_user_type_required'));
            }
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $campaign = MarketingCampaign::create([
                'title' => $request->input('title'),
                'subject' => $request->input('subject'),
                'content' => $request->input('content'),
                'landing_url' => $request->input('landing_url') ?: null,
                'email_enabled' => $request->boolean('email_enabled'),
                'in_app_enabled' => $request->boolean('in_app_enabled'),
                'target_type' => $request->input('target_type'),
                'target_user_ids' => $this->parseManualTargets($request),
                'target_user_type' => $request->input('target_user_type'),
                'status' => MarketingCampaign::STATUS_DRAFT,
                'created_by' => me()->id,
            ]);
        } catch (Throwable $th) {
            return redirect()->back()->withErrors(['title' => $th->getMessage()])->withInput();
        }

        return redirect()->route('admin.marketing.show', $campaign->id)
            ->with('flashMessage', (new Flash(content: __('admin/flash.marketing.created')))->get());
    }

    public function show(int $id)
    {
        $campaign = MarketingCampaign::query()
            ->withCount('recipients')
            ->findOrFail($id);

        $counts = [
            'email_pending_queued' => $campaign->recipients()->whereIn('email_status', ['pending', 'queued'])->count(),
            'email_sent' => $campaign->recipients()->where('email_status', 'sent')->count(),
            'email_failed' => $campaign->recipients()->where('email_status', 'failed')->count(),
            'email_skipped' => $campaign->recipients()->where('email_status', 'skipped')->count(),
            'in_app_pending_queued' => $campaign->recipients()->whereIn('in_app_status', ['pending', 'queued'])->count(),
            'in_app_sent' => $campaign->recipients()->where('in_app_status', 'sent')->count(),
            'in_app_failed' => $campaign->recipients()->where('in_app_status', 'failed')->count(),
            'in_app_skipped' => $campaign->recipients()->where('in_app_status', 'skipped')->count(),
        ];

        return view('admin::marketing.show.show', [
            'campaign' => $campaign,
            'counts' => $counts,
        ]);
    }

    public function send(int $id, Request $request)
    {
        $this->authorizeCampaignAccess();

        $campaign = MarketingCampaign::query()->findOrFail($id);

        $this->campaignService->start($campaign);

        return redirect()->route('admin.marketing.show', $campaign->id)
            ->with('flashMessage', (new Flash(content: __('admin/flash.marketing.started')))->get());
    }

    public function cancel(int $id, Request $request)
    {
        $this->authorizeCampaignAccess();

        $campaign = MarketingCampaign::query()->findOrFail($id);

        $this->campaignService->cancel($campaign);

        return redirect()->route('admin.marketing.show', $campaign->id)
            ->with('flashMessage', (new Flash(content: __('admin/flash.marketing.cancelled')))->get());
    }

    /**
     * 手动目标列表：按行解析用户名或数字ID，去空去重。
     */
    protected function parseManualTargets(Request $request): ?array
    {
        if ($request->input('target_type') !== MarketingCampaign::TARGET_MANUAL) {
            return null;
        }

        $identifiers = preg_split('/[\r\n,]+/', trim((string) $request->input('target_user_ids')));

        $identifiers = array_values(array_unique(array_filter(array_map('trim', $identifiers))));

        return $identifiers ?: null;
    }

    /**
     * 营销发送权限控制：send_root_only 开启时仅 root 可创建/发送/取消。
     */
    protected function authorizeCampaignAccess(): void
    {
        if (config('notifications.marketing.send_root_only', false) && ! (auth_check() && me()->isRoot())) {
            abort(403, __('admin/marketing.validation.root_only'));
        }
    }
}