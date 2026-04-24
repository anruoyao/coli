<template>
	<div v-if="followRecommendations.length" class="mb-4">
		<template v-if="state.isLoading">
			<div class="flex justify-center py-8">
				<PrimarySpinAnimation></PrimarySpinAnimation>
			</div>
		</template>
		<template v-else>
			<div class="mb-2">
				<h5 class="text-lab-pr2 font-semibold text-par-l">
					{{ $t('labels.follow_recommendations') }}
				</h5>
			</div>
			<div>
				<FollowListItem v-for="userData in followRecommendations" v-bind:key="userData.id" v-bind:userData="userData"></FollowListItem> 
			</div>

			<div>
				<RouterLink v-bind:to="{ name: 'explore_people' }" class="text-par-s hover:underline text-lab-sc cursor-pointer">
					{{ $t('labels.more_suggestions') }}
				</RouterLink>
			</div>
		</template>
	</div>
</template>

<script>
	import { defineComponent, reactive, onMounted, computed } from 'vue';
	import { useRecommendStore } from '@D/store/recommend/recommend.store.js';

	import FollowListItem from '@D/components/recommend/follow/list/FollowListItem.vue';
	
	export default defineComponent({
		setup: function(props, context) {
			const recommendStore = useRecommendStore();

			const state = reactive({
				isLoading: true
			});

			const followRecommendations = computed(() => {
				return recommendStore.followRecommendations;
			});

			onMounted(async () => {
				await recommendStore.fetchFollowRecommendations();

				state.isLoading = false;
			});

			return {
				state: state,
				followRecommendations: followRecommendations,
			};
		},
		components: {
			FollowListItem: FollowListItem
		}
	});
</script>