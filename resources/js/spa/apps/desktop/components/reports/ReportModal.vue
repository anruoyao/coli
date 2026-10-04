<template>
	<Teleport v-if="state.isOpen" to="body">
		<ContentModal v-on:close="closeReportModal">
			<ModalHeader v-bind:modalTitle="$t('labels.report')"></ModalHeader>

			<div v-if="state.isLoading" class="flex justify-center py-12">
				<PrimaryDotsAnimation></PrimaryDotsAnimation>
			</div>
			<div class="block" v-else>
				<div class="block py-6 px-4 text-center">
					<div class="flex justify-center">
						<div class="size-6 text-red-500">
							<SvgIcon name="alert-hexagon"></SvgIcon>
						</div>
					</div>
					<h3 class="text-title-3 font-bold text-lab-pr2">
						{{ reportInfo.title }}
					</h3>
					<p class="text-par-s text-lab-sc">
						{{ reportInfo.description }}
					</p>
				</div>
				<div v-if="state.rateLimitMessage" class="block px-4 pb-5">
					<div class="flex items-start gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3.5">
						<div class="size-5 shrink-0 text-amber-500">
							<SvgIcon name="alert-hexagon"></SvgIcon>
						</div>
						<div class="block min-w-0">
							<h4 class="text-par-s font-bold text-lab-pr2 text-left">
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
				<Border height="h-3"></Border>
				<div class="block max-h-96 overflow-y-auto">
					<ReasonItem v-on:click="selectReason(idx)" v-bind:isSelected="state.selectedReasonIndex === idx" v-for="(reasonData, idx) in reportInfo.reasons" v-bind:key="idx" v-bind:reasonData="reasonData"></ReasonItem>
				</div>
				<Border></Border>
				<div class="block py-4 px-4">
					<SoloTextInput
						v-model:modelValue="state.comment"
						v-bind:labelText="$t('labels.report_comment')"
						v-bind:placeholder="$t('labels.report_comment_placeholder')"
						v-bind:textLength="500"
						v-bind:inputError="state.commentError"></SoloTextInput>
					<div class="mt-4"></div>
					<PrimaryPillButton
						v-bind:loading="state.isSending"
						v-bind:isDisabled="state.selectedReasonIndex === null || state.rateLimitSeconds > 0"
						v-bind:buttonText="$t('labels.send_report')"
						buttonType="button"
						v-on:click="sendReport"
					v-bind:buttonFluid="true"></PrimaryPillButton>
				</div>
			</div>
		</ContentModal>
	</Teleport>
</template>

<script>
	import { computed, defineComponent, onMounted, onUnmounted, reactive } from 'vue';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useReportStore } from '@/apps/desktop/store/report/report.store.js';

	import ContentModal from '@D/components/general/modals/ContentModal.vue';
	import ModalHeader from '@D/components/general/modals/parts/ModalHeader.vue';
	import ReasonItem from '@D/components/reports/parts/ReasonItem.vue';
	import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
	import SoloTextInput from '@D/components/forms/solo/SoloTextInput.vue';

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

				document.body.style.overflow = 'hidden';
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

				document.body.style.overflow = 'auto';

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
			ContentModal: ContentModal,
			ModalHeader: ModalHeader,
			ReasonItem: ReasonItem,
			PrimaryPillButton: PrimaryPillButton,
			SoloTextInput: SoloTextInput
		}
	});
</script>
