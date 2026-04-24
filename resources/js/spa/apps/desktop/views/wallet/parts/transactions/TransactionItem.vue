<template>
	<div class="flex gap-4 items-center border-b border-bord-pr py-3 last:border-none hover:bg-fill-fv cursor-pointer">
		<div class="size-normal-avatar rounded-full bg-fill-qt inline-flex-center shrink-0">
			<SvgIcon v-bind:name="iconName" classes="size-icon-normal text-brand-900"></SvgIcon>
		</div>
		<div class="flex flex-1 justify-between">
			<div class="flex-1">
				<span class="block text-par-l text-lab-pr2 font-medium">
					{{ transactionData.source.name }}
				</span>
				<span class="block text-par-s text-brand-900">
					{{ transactionData.type.label }}
				</span>
				<span class="block text-par-s text-lab-sc" v-if="hasCommission">
					{{ $t('wallet.commission') }} {{ transactionData.commission.amount.formatted }}
				</span>
			</div>
			<div class="shrink-0 text-right">
				<span class="block text-par-m font-medium" v-bind:class="[transactionData.is_incoming ? 'text-mint' : 'text-red-900']">
					{{ transactionData.is_incoming ? '+' : '-' }}{{ transactionData.amount.formatted }}
				</span>
				<span class="block text-par-s text-lab-sc">
					{{ transactionData.date.time_ago }}
				</span>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';

	export default defineComponent({
		props: {
			transactionData: {
				type: Object,
				required: true
			}
		},
		setup: function(props) {
			return {
				iconName: computed(() => {
					if (props.transactionData.type.key === 'deposit') {
						return 'credit-card-up';
					}
					
					else {
						if(props.transactionData.is_incoming) {
							return 'arrow-narrow-down-left';
						}

						return 'arrow-narrow-up-right';
					}
				}),
				hasCommission: computed(() => {
					return props.transactionData.commission.rate > 0;
				})
			}
		}
	});
</script>