<template>
	<template v-if="state.isLoading">
		<OverviewSkeleton></OverviewSkeleton>
	</template>
	<template v-else>
		<div class="block">
			<div class="flex mb-2.5 items-end">
				<div class="flex-1">
					<h2 class="text-5xl 2xl:text-7xl leading-none font-bold text-mint">
						{{ walletBalance() }}
					</h2>
					<span class="inline-flex items-center">
						<span class="text-par-n text-lab-sc">
							{{ $t('wallet.current_balance') }}
						</span>
						<span v-if="state.isBalanceVisible" v-on:click="hideBalance" class="shrink-0 size-4 ml-1 cursor-pointer">
							<SvgIcon name="eye-off" classes="size-full text-lab-tr"></SvgIcon>
						</span>
						<span v-else v-on:click="showBalance" class="shrink-0 size-4 ml-1 cursor-pointer">
							<SvgIcon name="eye" classes="size-full text-lab-tr"></SvgIcon>
						</span>
					</span>
				</div>
				<div class="shrink-0">
					<PrimaryIconButton
						v-on:click="state.isWalletInfoModalOpen = true"
						iconName="info-circle"
						iconSize="icon-small"
					iconType="line"></PrimaryIconButton>
				</div>
			</div>
			<div class="grid grid-cols-3 gap-2">
				<div v-on:click="state.isDepositModalOpen = true" class="h-40 bg-fill-qt rounded-xl px-4 py-3 relative cursor-pointer hover:bg-fill-tr transition-all ease-linear">
					<h4 class="text-par-n block text-lab-pr2 font-bold mb-1">
						{{ $t('labels.deposit') }}
					</h4>
					<p class="text-par-s block leading-tight text-lab-sc" v-html="$t('wallet.add_money_to_wallet')"></p>
	
					<span class="size-10 rounded-xl bg-brand-900 inline-flex-center absolute bottom-4 right-4">
						<SvgIcon name="plus" classes="size-icon-small text-white"></SvgIcon>
					</span>
				</div>
				<div v-on:click="state.isTransferModalOpen = true" class="h-40 bg-fill-qt rounded-xl px-4 py-3 relative cursor-pointer hover:bg-fill-tr transition-all ease-linear">
					<h4 class="text-par-n block text-lab-pr2 font-bold mb-1">
						{{ $t('labels.transfer') }}
					</h4>
					<p class="text-par-s leading-tight inline-block text-lab-sc" v-html="$t('wallet.send_to_another')"></p>
					<span class="size-10 rounded-xl bg-white inline-flex-center absolute bottom-4 right-4">
						<SvgIcon name="arrow-narrow-right" classes="size-icon-small text-brand-900"></SvgIcon>
					</span>
				</div>
				<div class="h-40 bg-fill-qt opacity-50 rounded-xl px-4 py-3 relative cursor-not-allowed hover:bg-fill-tr transition-all ease-linear">
					<h4 class="text-par-n block text-lab-pr2 font-bold mb-1">
						{{ $t('labels.withdrawal') }}
					</h4>
					<p class="text-par-s inline-block text-lab-sc leading-tight" v-html="$t('wallet.request_withdrawal')"></p>
					<span class="size-10 rounded-xl bg-white inline-flex-center absolute bottom-4 right-4">
						<SvgIcon name="credit-card-up" classes="size-icon-small text-brand-900"></SvgIcon>
					</span>
				</div>
			</div>
			<div class="mt-2">
				<p class="text-cap-s text-lab-sc" v-html="$t('wallet.about_wallet_text', { wallet_name: $embedder('config.wallet.name'), about_link: $embedder('config.wallet.about_link') })"></p>
			</div>
		</div>
		<Teleport to="body">
			<PrimaryTransition>
				<DepositModal v-if="state.isDepositModalOpen" v-on:close="state.isDepositModalOpen = false"></DepositModal>
			</PrimaryTransition>
			<PrimaryTransition>
				<TransferModal
					v-if="state.isTransferModalOpen"
				v-on:close="state.isTransferModalOpen = false"></TransferModal>
			</PrimaryTransition>
			<PrimaryTransition>
				<WalletInfoModal v-if="state.isWalletInfoModalOpen" v-on:close="state.isWalletInfoModalOpen = false"></WalletInfoModal>
			</PrimaryTransition>
		</Teleport>
	</template>
</template>


<script>
	import { defineComponent, ref, defineAsyncComponent, reactive, onMounted, computed } from 'vue';
	import { useWalletStore } from '@D/store/wallet/wallet.store.js';

	import OverviewSkeleton from '@D/views/wallet/parts/skeletons/OverviewSkeleton.vue';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';

	export default defineComponent({
		setup: function() {
			const state = reactive({
				isLoading: true,
				isDepositModalOpen: false,
				isTransferModalOpen: false,
				isBalanceVisible: true,
				isWalletInfoModalOpen: false
			});

			const walletStore = useWalletStore();

            const walletData = computed(() => {
                return walletStore.walletData;
            });

			onMounted(async () => {
				if(localStorage.getItem('hide_wallet_balance')) {
					state.isBalanceVisible = false;
				}
				else{
					state.isBalanceVisible = true;
				}

				await walletStore.fetchWalletData();

				state.isLoading = false;
			});

			return {
				state: state,
				walletData: walletData,
                walletBalance: () => {
                    if(state.isBalanceVisible) {
						return walletData.value.balance.formatted;
                    }
                    else{
						return "*".repeat(walletData.value.balance.formatted.length);
                    }
                },
				showBalance: () => {
					localStorage.removeItem('hide_wallet_balance');
					state.isBalanceVisible = true;
				},
                hideBalance: () => {
                    localStorage.setItem('hide_wallet_balance', 1);
                    state.isBalanceVisible = false;
                }
			}
		},
		components: {
			PrimaryIconButton: PrimaryIconButton,
			OverviewSkeleton: OverviewSkeleton,
			DepositModal: defineAsyncComponent(() => {
                return import('@D/views/wallet/parts/modals/DepositModal.vue');
            }),
            TransferModal: defineAsyncComponent(() => {
                return import('@D/views/wallet/parts/modals/TransferModal.vue');
            }),
			WalletInfoModal: defineAsyncComponent(() => {
                return import('@D/views/wallet/parts/modals/WalletInfoModal.vue');
            })
		}
	});
</script>