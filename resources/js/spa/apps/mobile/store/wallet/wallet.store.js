import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const useWalletStore = defineStore('mobile_wallet_store', {
    state: function() {
		return {
			walletData: null
		}
	},
    actions: {
		fetchWalletData: async function() {
			await colibriAPI().wallet().getFrom('data').then((response) => {
				this.walletData = response.data.data;
			}).catch((error) => {
				if(error.response) {
					alert(error.response.data.message);
				}
			});
		}
    }
});

export { useWalletStore };