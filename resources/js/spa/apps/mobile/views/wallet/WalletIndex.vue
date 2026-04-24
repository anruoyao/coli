<template>
	<Toolbar v-on:close="$router.back" v-bind:title="$t('labels.wallet')">
		
	</Toolbar>
	<template v-if="state.isLoading">
		<OverviewSkeleton></OverviewSkeleton>
	</template>
	<template v-else>
		<div class="px-4 py-4 bg-fill-fv">
			<div class="p-4 bg-bg-pr rounded-2xl mb-4">
				<div class="mb-6">
					<div class="flex items-center">
						<h2 class="text-title-1 leading-none font-bold text-mint">
							{{ walletBalance() }}
						</h2>
						<div class="shrink-0 ml-auto inline-flex gap-2">
							<PrimaryIconButton
								v-if="! state.balanceStatus"
								v-on:click="showBalance"
							iconName="eye" iconType="line"></PrimaryIconButton>
							<PrimaryIconButton
								v-else
								v-on:click="hideBalance"
							iconName="eye-off" iconType="line"></PrimaryIconButton>
	
							<PrimaryIconButton
								v-on:click="state.infoMenu.open"
								iconName="info-circle"
							iconType="line"></PrimaryIconButton>
						</div>
					</div>
					<span class="text-par-n text-lab-sc">
						{{ $t('wallet.current_balance') }}
					</span>
				</div>
				<p class="text-par-s text-lab-sc" v-html="$t('wallet.about_wallet_text', { wallet_name: $embedder('config.wallet.name'), about_link: $embedder('config.wallet.about_link') })"></p>
			</div>
			<div v-on:click="state.controlMenu.open" class="flex gap-4 hidden-scroll mb-4">
				<div class="h-40 bg-bg-pr opacity-70 rounded-xl px-4 py-3 relative w-5/12 shrink-0">
					<h4 class="text-par-n block text-lab-pr2 font-bold mb-1">
						{{ $t('labels.deposit') }}
					</h4>
					<p class="text-par-s block leading-tight text-lab-sc" v-html="$t('wallet.add_money_to_wallet')"></p>
	
					<span class="size-10 rounded-xl bg-brand-900 inline-flex-center absolute bottom-4 right-4">
						<SvgIcon name="plus" classes="size-icon-small text-white"></SvgIcon>
					</span>
				</div>
				<div class="h-40 bg-bg-pr opacity-70 rounded-xl px-4 py-3 relative w-5/12 shrink-0">
					<h4 class="text-par-n block text-lab-pr2 font-bold mb-1">
						{{ $t('labels.transfer') }}
					</h4>
					<p class="text-par-s leading-tight inline-block text-lab-sc" v-html="$t('wallet.send_to_another')"></p>
					<span class="size-10 rounded-xl bg-white inline-flex-center absolute bottom-4 right-4">
						<SvgIcon name="arrow-narrow-right" classes="size-icon-small text-brand-900"></SvgIcon>
					</span>
				</div>
				<div class="h-40 bg-bg-pr opacity-70 rounded-xl px-4 py-3 relative w-5/12 shrink-0">
					<h4 class="text-par-n block text-lab-pr2 font-bold mb-1">
						{{ $t('labels.withdrawal') }}
					</h4>
					<p class="text-par-s inline-block text-lab-sc leading-tight" v-html="$t('wallet.request_withdrawal')"></p>
					<span class="size-10 rounded-xl bg-white inline-flex-center absolute bottom-4 right-4">
						<SvgIcon name="credit-card-up" classes="size-icon-small text-brand-900"></SvgIcon>
					</span>
				</div>
			</div>

			<div class="p-4 bg-bg-pr rounded-2xl">
				<h4 class="text-par-m font-bold mb-1 text-lab-pr2">
					{{ $t('info.balance_preview_only.title') }}
				</h4>
				<p class="text-par-m text-lab-sc">
					{{ $t('info.balance_preview_only.desc') }}
				</p>
			</div>
		</div>
	</template>

	<Teleport to="body">
		<ActionSheet v-if="state.infoMenu.status" v-on:close="state.infoMenu.close" v-bind:isMuted="true">
			<div class="text-center pb-4">
				<SheetTitle v-bind:title="$t('wallet.wallet_info')"></SheetTitle>
			</div>
			<ActionSheetGroup>
				<div class="pb-2">
					<InfoList v-bind:hasTitle="false">
						<InfoListItem 
							iconName="wallet-02"
							v-bind:labelText="$t('wallet.wallet_address')"
							v-bind:isCopyable="true"
						v-bind:contentText="walletData.wallet_number"></InfoListItem>
						<Border></Border>
						<InfoListItem 
							iconName="currency-euro"
							v-bind:labelText="$t('labels.currency')"
						v-bind:contentText="`${walletData.currency.name} (${walletData.currency.symbol})`"></InfoListItem>
					</InfoList>
				</div>
			</ActionSheetGroup>
		</ActionSheet>

		<ActionSheet v-if="state.controlMenu.status" v-on:close="state.controlMenu.close">
			<SheetInfo v-on:close="state.controlMenu.close" v-bind:sheetTitle="$t('info.only_desktop.title')" v-bind:sheetDesc="$t('info.only_desktop.wallet_control')"></SheetInfo>
		</ActionSheet>
	</Teleport>
</template>


<script>
	import { defineComponent, ref, defineAsyncComponent, reactive, onMounted, computed } from 'vue';
	import { useWalletStore } from '@M/store/wallet/wallet.store.js';
	import { useMenu } from '@/kernel/vue/composables/menu/index.js';

	import Toolbar from '@M/components/layout/Toolbar.vue';
	import OverviewSkeleton from '@M/views/wallet/parts/skeletons/OverviewSkeleton.vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import SheetTitle from '@M/components/general/sheets/SheetTitle.vue';
	import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';
	import SheetInfo from '@M/components/general/sheets/SheetInfo.vue';
	import InfoList from '@M/components/general/info/InfoList.vue';
	import InfoListItem from '@M/components/general/info/InfoListItem.vue';

	export default defineComponent({
		setup: function() {
			const state = reactive({
				isLoading: true,
				balanceStatus: true,
				infoMenu: useMenu(),
				controlMenu: useMenu()
			});

			const walletStore = useWalletStore();

            const walletData = computed(() => {
                return walletStore.walletData;
            });

			onMounted(async () => {
				if(localStorage.getItem('hide_wallet_balance')) {
					state.balanceStatus = false;
				}
				else{
					state.balanceStatus = true;
				}

				await walletStore.fetchWalletData();

				state.isLoading = false;
			});

			return {
				state: state,
				walletData: walletData,
                walletBalance: () => {
                    if(state.balanceStatus) {
						return walletData.value.balance.formatted;
                    }
                    else{
						return "*".repeat(walletData.value.balance.formatted.length);
                    }
                },
				showBalance: () => {
					localStorage.removeItem('hide_wallet_balance');
					state.balanceStatus = true;
				},
                hideBalance: () => {
                    localStorage.setItem('hide_wallet_balance', 1);
                    state.balanceStatus = false;
                }
			}
		},
		components: {
			PrimaryIconButton: PrimaryIconButton,
			OverviewSkeleton: OverviewSkeleton,
			Toolbar: Toolbar,
			ActionSheet: ActionSheet,
			ActionSheetGroup: ActionSheetGroup,
			SheetInfo: SheetInfo,
			SheetTitle: SheetTitle,
			InfoList: InfoList,
			InfoListItem: InfoListItem
		}
	});
</script>