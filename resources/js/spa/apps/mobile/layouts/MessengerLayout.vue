<template>
	<RouterView></RouterView>

	<ConfirmationModal></ConfirmationModal>

	<ReportModal></ReportModal>

    <LightboxPlayer></LightboxPlayer>
</template>

<script>
	import { defineComponent } from 'vue';

	import ConfirmationModal from '@M/components/general/modals/prompt/ConfirmationModal.vue';

	import ReportModal from '@M/components/reports/ReportModal.vue';
	import LightboxPlayer from '@M/components/lightbox/LightboxPlayer.vue';

	export default defineComponent({
		components: {
			ConfirmationModal: ConfirmationModal,
			ReportModal: ReportModal,
			LightboxPlayer: LightboxPlayer
		}
	});
</script>
