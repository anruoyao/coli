<template>
	<PrimaryPillButton
		v-on:click="handleFollowDecline"
		v-bind="$attrs"
		v-bind:loading="isLoading"
		v-bind:buttonText="$t('labels.follow_decline_button')"
	buttonRole="marginal"></PrimaryPillButton>
</template>

<script>
    import { defineComponent, ref } from 'vue';
    import { colibriFollow } from '@/kernel/services/follows/index.js';

    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';

    export default defineComponent({
        props: {
			followableId: {
				type: Number,
				default: 0
			}
        },
        setup: function(props) {
			const isLoading = ref(false);

			return {
				isLoading: isLoading,
				handleFollowDecline: () => {
					isLoading.value = true;

					colibriFollow().user(props.followableId).decline();

					isLoading.value = false;
				}
			}
        },
        components: {
			PrimaryPillButton: PrimaryPillButton
        }
    });
</script>
