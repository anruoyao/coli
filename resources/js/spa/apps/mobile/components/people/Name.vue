<template>
	<h3 class="text-par-n font-semibold truncate" v-bind:class="[color]" v-bind:style="{ color: color }">
		{{ name }}
		<VerificationBadge v-if="isVerified" size="xs"></VerificationBadge>
		<span v-if="showPin" class="size-icon-x-small inline-block text-brand-900 ml-1">
			<SvgIcon name="pin-02"></SvgIcon>
		</span>
	</h3>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			name: {
				type: String,
				required: true
			},
			color: {
				type: String,
				default: 'text-lab-pr2'
			},
			isVerified: {
				type: Boolean,
				default: false
			},
			showPin: {
				type: Boolean,
				default: false
			}
		}
	});
</script>