<template>
	<RouterView></RouterView>

	<ConfirmationModal></ConfirmationModal>

	<ReportModal></ReportModal>
</template>

<script>
	import { defineComponent } from 'vue';
	
	import ConfirmationModal from '@M/components/general/modals/prompt/ConfirmationModal.vue';

	import ReportModal from '@M/components/reports/ReportModal.vue';

	export default defineComponent({
		components: {
			ConfirmationModal: ConfirmationModal,
			ReportModal: ReportModal
		}
	});
</script>