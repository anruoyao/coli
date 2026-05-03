<template>
	<RouterView></RouterView>

	<AccountSwitcherModal></AccountSwitcherModal>

	<ConfirmationModal></ConfirmationModal>

	<ReportModal></ReportModal>

	<ToastNotification></ToastNotification>
</template>

<script>
	import { defineComponent } from 'vue';

	import ReportModal from '@D/components/reports/ReportModal.vue';
	import ConfirmationModal from '@D/components/general/modals/prompt/ConfirmationModal.vue';
	import ToastNotification from '@D/components/notifications/toast/ToastNotification.vue';
	import AccountSwitcherModal from '@D/components/accounts/AccountSwitcherModal.vue';

	export default defineComponent({
		components: {
			AccountSwitcherModal: AccountSwitcherModal,
			ConfirmationModal: ConfirmationModal,
			ReportModal: ReportModal,
			ToastNotification: ToastNotification
		}
	});
</script>