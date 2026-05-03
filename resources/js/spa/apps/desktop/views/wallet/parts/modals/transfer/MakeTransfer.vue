<template>
	<div class="p-4">
		<div class="flex justify-center mb-4">
			<div class="size-16 rounded-full bg-fill-qt inline-flex-center shrink-0">
				<SvgIcon name="arrow-narrow-up-right" classes="size-6 text-brand-900"></SvgIcon>
			</div>
		</div>
		<h4 class="text-center text-par-l text-lab-pr font-medium leading-none">
			{{ receiverData.relations.user.name }}
		</h4>
		<div class="text-center mb-6">
			<span class="text-lab-sc text-par-s">
				{{ $t('wallet.transfer') }}
			</span>
		</div>
		<div class="block">
			<ModalTextInput v-bind:labelText="$t('wallet.transfer_amount')"
				v-bind:placeholder="$money('0, 00')"
				inputType="number"
				v-bind:inputErrors="state.formErrors"
				v-on:clear="clearAmount"
			v-model="formData.amount">
				<template v-slot:feedbackInfo>
					{{ formData.amount ? $t('wallet.transfer_commission_amount', { commission_amount: $money(commissionAmount)} ) : $t('wallet.transfer_commission_helper') }}
				</template>
			</ModalTextInput>
		</div>
	</div>
	<div class="block mb-4">
		<Border height="h-3" opacity="opacity-70"></Border>
		<div class="block pb-3">
			<textarea
				v-model="formData.message"
				class="resize-none w-full pl-4 pr-12 leading-5 pt-3.5 pb-6 bg-transparent text-par-n text-lab-pr2 max-h-96 overflow-y-auto min-h-28 outline-hidden placeholder:font-light placeholder:text-par-s" 
			v-bind:placeholder="$t('wallet.transfer_message')"></textarea>
			<span class="block text-cap-l px-4"
			v-bind:class="[formData.message.length > 140 ? 'text-red-900' : 'text-lab-sc']">{{ formData.message.length }}/140</span>
		</div>
		<Border></Border>
	</div>
	
	<div class="mb-12 px-4">
		<PrimaryPillButton
			v-on:click="makeTransfer"
			v-bind:loading="state.isSubmitting"
			buttonType="button"
			v-bind:buttonFluid="true"
			buttonRole="accent"
			buttonSize="lm"
			v-bind:buttonText="$t('wallet.make_transfer')"
		v-bind:disabled="! isValidForm">
		</PrimaryPillButton>
		<div v-if="! state.isSubmitting" class="flex justify-center mt-4">
			<PrimaryTextButton
				buttonRole="marginal"
				v-on:click="$emit('cancel')"
			v-bind:buttonText="$t('wallet.reselect_receiver')"></PrimaryTextButton>
		</div>
	</div>
</template>

<script>
	import { defineComponent, reactive, computed } from 'vue';
	import { useWalletStore } from '@D/store/wallet/wallet.store.js';

	import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
	import PrimaryTextButton from '@D/components/inter-ui/buttons/PrimaryTextButton.vue';
	import ModalTextInput from '@D/components/forms/modal/ModalTextInput.vue';

	export default defineComponent({
		props: {
			receiverData: {
				type: Object,
				required: true
			}
		},
		emits: ['cancel', 'close'],
		setup: function(props, context) {
			const state = reactive({
				isSubmitting: false,
				formErrors: []
			});

			const walletStore = useWalletStore();

			const formData = reactive({
				amount: '',
				message: ''
			});

			return {
				formData: formData,
				state: state,
				commissionAmount: computed(() => {
					return formData.amount * config('payment.commission.transfer') / 100;
				}),
				makeTransfer: function() {
					state.isSubmitting = true;
					state.formErrors = [];

					walletStore.makeTransfer({
						amount: formData.amount,
						wallet_number: props.receiverData.wallet_number,
						message: formData.message
					}).then((response) => {
						toastSuccess(__t('toast.wallet.transfer.success'));

						walletStore.fetchTransactions();

						context.emit('close');
					}).catch((error) => {
						if(error.response) {
							state.formErrors = error.response.data.errors.amount;
						}
					});

					state.isSubmitting = false;
				},
				isValidForm: computed(() => {
					return formData.amount && formData.amount > 0 && formData.message.length <= 140;
				}),
				clearAmount: function() {
					formData.amount = '';
					state.formErrors = [];
				}
			}
		},
		components: {
			PrimaryPillButton: PrimaryPillButton,
			ModalTextInput: ModalTextInput,
			PrimaryTextButton: PrimaryTextButton
		}
	});
</script>
