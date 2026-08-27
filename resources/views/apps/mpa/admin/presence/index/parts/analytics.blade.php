@php
    // 图表数据由 PresenceService::activityStats() 提供（快照由 presence:aggregate 每小时写入）
    $hourlyLabels  = array_column($stats['hourly'], 'label');
    $hourlyTotal   = array_column($stats['hourly'], 'total');
    $hourlyWeb     = array_column($stats['hourly'], 'web');
    $hourlyAndroid = array_column($stats['hourly'], 'android');
    $hourlyIos     = array_column($stats['hourly'], 'ios');

    $dailyLabels = array_column($stats['daily'], 'label');
    $dailyTotal  = array_column($stats['daily'], 'total');
    $dailyPeak   = array_column($stats['daily'], 'peak');
@endphp

{{-- 活跃指标：DAU / WAU / MAU / 今日峰值 --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
    @foreach ([
        ['label' => __('admin/presence.analytics.dau'),     'value' => $stats['dau']],
        ['label' => __('admin/presence.analytics.wau'),     'value' => $stats['wau']],
        ['label' => __('admin/presence.analytics.mau'),     'value' => $stats['mau']],
        ['label' => __('admin/presence.analytics.today_peak'), 'value' => $stats['todayPeak']],
    ] as $kpi)
        <div class="bg-bg-pr rounded-2xl p-6 border border-bord-sc">
            <span class="text-par-s block text-lab-sc">{{ $kpi['label'] }}</span>
            <span class="text-title-3 block text-lab-pr2 font-bold font-outfit tracking-tight mt-1">{{ $kpi['value'] }}</span>
        </div>
    @endforeach
</div>

@if (empty($stats['hourly']))
    <div class="bg-bg-pr rounded-2xl p-10 border border-bord-sc text-center">
        <p class="text-par-m text-lab-sc">{{ __('admin/presence.analytics.empty_hint') }}</p>
    </div>
@else
    {{-- 24h 在线趋势（分平台） --}}
    <div class="bg-bg-pr rounded-2xl p-6 border border-bord-sc mb-4">
        <p class="text-par-m font-bold text-lab-pr">{{ __('admin/presence.analytics.hourly_trend') }}</p>
        <p class="text-par-s text-lab-sc mb-4">{{ __('admin/presence.analytics.hourly_hint') }}</p>
        <div id="presence-hourly-chart" class="w-full"></div>
    </div>

    {{-- 近 7 日在线总量 --}}
    <div class="bg-bg-pr rounded-2xl p-6 border border-bord-sc">
        <p class="text-par-m font-bold text-lab-pr">{{ __('admin/presence.analytics.daily_trend') }}</p>
        <p class="text-par-s text-lab-sc mb-4">{{ __('admin/presence.analytics.daily_hint') }}</p>
        <div id="presence-daily-chart" class="w-full"></div>
    </div>
@endif

<script>
    window.addEventListener('load', () => {
        const hourlyEl = document.querySelector('#presence-hourly-chart');
        if (hourlyEl) {
            new ApexCharts(hourlyEl, {
                chart: { type: 'area', height: 260, toolbar: { show: false }, fontFamily: 'inherit' },
                series: [
                    { name: @json(__('admin/presence.analytics.series_total')), data: @json($hourlyTotal) },
                    { name: 'Web', data: @json($hourlyWeb) },
                    { name: 'Android', data: @json($hourlyAndroid) },
                    { name: 'iOS', data: @json($hourlyIos) },
                ],
                colors: ['#40E378', '#3B82F6', '#8B5CF6', '#F59E0B'],
                stroke: { width: 2, curve: 'smooth' },
                fill: { type: 'gradient', gradient: { opacityFrom: 0.18, opacityTo: 0.02, stops: [0, 90] } },
                dataLabels: { enabled: false },
                xaxis: { categories: @json($hourlyLabels), labels: { rotate: -45 } },
                legend: { position: 'top', horizontalAlign: 'right' },
                grid: { borderColor: 'var(--border)', strokeDashArray: 3 },
                tooltip: { shared: true, intersect: false },
            }).render();
        }

        const dailyEl = document.querySelector('#presence-daily-chart');
        if (dailyEl) {
            new ApexCharts(dailyEl, {
                chart: { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'inherit' },
                series: [
                    { name: @json(__('admin/presence.analytics.series_daily_total')), data: @json($dailyTotal) },
                    { name: @json(__('admin/presence.analytics.series_daily_peak')), data: @json($dailyPeak) },
                ],
                colors: ['#40E378', '#3B82F6'],
                plotOptions: { bar: { columnWidth: '55%', borderRadius: 4 } },
                dataLabels: { enabled: false },
                xaxis: { categories: @json($dailyLabels) },
                legend: { position: 'top', horizontalAlign: 'right' },
                grid: { borderColor: 'rgba(163,163,163,0.28)', strokeDashArray: 3 },
            }).render();
        }
    });
</script>

@push('scripts')
    @vite('resources/js/mpa/apexcharts.js')
@endpush