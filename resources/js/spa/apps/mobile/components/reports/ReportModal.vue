<template>
	<ActionSheet v-if="state.isOpen" v-on:close="closeReportModal">
		<div class="flex flex-col h-full">
			<div class="px-12 text-center pb-4 shrink-0">
				<h3 class="text-par-l font-bold text-lab-pr mb-1">
					{{ reportInfo.title }}
				</h3>
				<p class="text-par-n text-lab-sc">
					{{ reportInfo.description }}
				</p>
			</div>
			<div v-if="state.rateLimitMessage" class="px-6 pb-4 shrink-0">
				<div class="flex items-start gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3.5">
					<div class="size-5 shrink-0 text-amber-500">
						<SvgIcon name="alert-hexagon"></SvgIcon>
					</div>
					<div class="block min-w-0">
						<h4 class="text-par-s font-bold text-lab-pr text-left">
							{{ $t('labels.report_rate_limited_title') }}
						</h4>
						<p class="text-par-s text-lab-sc mt-1 text-left">
							{{ state.rateLimitMessage }}
						</p>
						<p v-if="state.rateLimitSeconds > 0" class="flex items-center gap-1.5 mt-2.5 text-par-s text-lab-sc">
							<span class="size-4 inline-block text-amber-500">
								<SvgIcon name="clock-check" type="line"></SvgIcon>
							</span>
							{{ $t('labels.report_countdown') }}
							<span class="font-bold text-amber-600 tabular-nums tracking-widest">{{ countdownText }}</span>
						</p>
					</div>
				</div>
			</div>
			<div class="flex-1 overflow-y-auto border-y border-y-bord-pr">
				<ReasonItem v-on:click="selectReason(idx)" v-bind:isSelected="state.selectedReasonIndex === idx" v-for="(reasonData, idx) in reportInfo.reasons" v-bind:key="idx" v-bind:reasonData="reasonData"></ReasonItem>
				<div class="block px-6 py-4">
					<TextInput
						v-model:modelValue="state.comment"
						v-bind:asText="true"
						v-bind:labelText="$t('labels.report_comment')"
						v-bind:placeholder="$t('labels.report_comment_placeholder')"
						v-bind:textLength="500"
						v-bind:inputErrors="commentErrors">
						<template v-slot:feedbackInfo>
							<span v-if="state.commentError" class="text-red-900">{{ state.commentError }}</span>
						</template>
					</TextInput>
				</div>
			</div>
			<div class="pt-4 px-6 shrink-0">
				<PrimaryPillButton
					v-bind:loading="state.isSending"
					v-bind:isDisabled="state.selectedReasonIndex === null || state.rateLimitSeconds > 0"
					v-bind:buttonText="$t('labels.send_report')"
					buttonType="button"
					buttonRole="danger"
					v-on:click="sendReport"
				v-bind:buttonFluid="true"></PrimaryPillButton>
			</div>
		</div>
	</ActionSheet>
</template>

<script>
	import { computed, defineComponent, onMounted, onUnmounted, reactive } from 'vue';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useReportStore } from '@M/store/report/report.store.js';

	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import ReasonItem from '@M/components/reports/parts/ReasonItem.vue';
	import TextInput from '@M/components/forms/TextInput.vue';
	import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';

	export default defineComponent({
		setup: function() {
			const state = reactive({
				isOpen: false,
				isLoading: true,
				reportType: null,
				selectedReasonIndex: null,
				comment: '',
				commentError: '',
				isSending: false,
				rateLimitMessage: '',
				rateLimitSeconds: 0
			});

			const reportStore = useReportStore();

			let countdownTimer = null;

			const stopCountdown = () => {
				if(countdownTimer) {
					clearInterval(countdownTimer);

					countdownTimer = null;
				}
			};

			const startCountdown = (seconds) => {
				stopCountdown();

				state.rateLimitSeconds = seconds;

				countdownTimer = setInterval(() => {
					state.rateLimitSeconds--;

					if(state.rateLimitSeconds <= 0) {
						state.rateLimitSeconds = 0;
						state.rateLimitMessage = '';

						stopCountdown();
					}
				}, 1000);
			};

			const countdownText = computed(() => {
				const totalSeconds = Math.max(0, state.rateLimitSeconds);
				const hours = Math.floor(totalSeconds / 3600);
				const minutes = Math.floor((totalSeconds % 3600) / 60);
				const seconds = totalSeconds % 60;

				return [hours, minutes, seconds].map((value) => String(value).padStart(2, '0')).join(':');
			});

			const openReportModal = async (data) => {
				state.isOpen = true;
				state.reportType = data.type;
				state.reportableId = data.reportableId;

				if(! reportStore.reportReasons[state.reportType]) {
					await reportStore.fetchReportReasons(state.reportType);
				}

				state.isLoading = false;
			};

			const closeReportModal = () => {
				state.isOpen = false;
				state.isLoading = true;
				state.reportType = null;
				state.reportableId = null;
				state.selectedReasonIndex = null;
				state.comment = '';
				state.commentError = '';
				state.isSending = false;

				// 限流状态刻意保留：冷却期内重新打开弹窗仍显示提示与剩余倒计时
			};

			onMounted(() => {
				colibriEventBus.on('report:open', openReportModal);
				colibriEventBus.on('report:close', closeReportModal);
			});

			onUnmounted(() => {
				colibriEventBus.off('report:open', openReportModal);
				colibriEventBus.off('report:close', closeReportModal);

				stopCountdown();
			});

			return {
				state: state,
				closeReportModal: closeReportModal,
				reportInfo: computed(() => {
					return reportStore.reportReasons[state.reportType];
				}),
				countdownText: countdownText,
				commentErrors: computed(() => {
					return state.commentError ? [state.commentError] : [];
				}),
				selectReason: function(idx) {
					if(state.selectedReasonIndex === idx) {
						state.selectedReasonIndex = null;
					} else {
						state.selectedReasonIndex = idx;
					}
				},
				sendReport: async function() {
					if(state.isSending || state.selectedReasonIndex === null || state.rateLimitSeconds > 0) {
						return;
					}

					state.isSending = true;
					state.commentError = '';

					const result = await reportStore.sendReport({
						type: state.reportType,
						reason_index: state.selectedReasonIndex,
						reportable_id: state.reportableId,
						comment: state.comment.trim() ? state.comment.trim() : null
					});

					state.isSending = false;

					if(result.success) {
						toastSuccess(__t('toast.report_sent'));

						closeReportModal();

						return;
					}

					if(result.status === 429 && result.rateLimit && result.rateLimit.retry_after) {
						state.rateLimitMessage = result.message;
						startCountdown(result.rateLimit.retry_after);

						return;
					}

					if(result.status === 422 && result.errors && result.errors.comment) {
						state.commentError = result.errors.comment[0];

						return;
					}

					toastError(result.message);
				}
			};
		},
		components: {
			ReasonItem: ReasonItem,
			ActionSheet: ActionSheet,
			TextInput: TextInput,
			PrimaryPillButton: PrimaryPillButton
		}
	});
</script>
