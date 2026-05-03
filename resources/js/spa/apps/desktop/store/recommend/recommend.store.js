import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const useRecommendStore = defineStore('recommend_store', {
    state: function() {
		return {
			followRecommendations: [],
		}
	},
    getters: {
    },
    actions: {
		fetchFollowRecommendations: async function() {
			const state = this;

			await colibriAPI().recommendations().getFrom('follow').then((response) => {
				state.followRecommendations = response.data.data;
			});
		}
    }
});

export { useRecommendStore };