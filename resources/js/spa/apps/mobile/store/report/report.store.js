import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const useReportStore = defineStore('mobile_report_store', {
    state: function() {
		return {
			reportReasons: {
				post: null,
				user: null
			}
		}
	},
    actions: {
		fetchReportReasons: async function(type) {
			await colibriAPI().feedback().params({
				type: type
			}).getFrom('report/reasons').then((response) => {
				this.reportReasons[type] = response.data.data;
			}).catch((error) => {
				if(error.response) {
					toastError(error.response.data.message);
				}
			});
		},
		sendReport: async function(reportData) {
			return await colibriAPI().feedback().with(reportData).sendTo('report/send').then((response) => {
				return {
					success: true,
					remaining: response.data.data.remaining ?? null
				};
			}).catch((error) => {
				if(error.response) {
					return {
						success: false,
						status: error.response.status,
						message: error.response.data.message,
						errors: error.response.data.errors,
						rateLimit: error.response.data.data
					};
				}

				throw error;
			});
		}
    }
});

export { useReportStore };
