<template>
	<Backdrop>
		<div class="ml-navbar py-top-offset">
			<StoriesEditor></StoriesEditor>
		</div>
	</Backdrop>
</template>

<script>
	import { defineComponent } from 'vue';
	import ColibriPlusAttribution from '@D/components/attributions/ColibriPlusAttribution.vue';
    import Backdrop from '@D/components/general/modals/Backdrop.vue';
	import StoriesEditor from '@D/components/stories/editor/StoriesEditor.vue';

	export default defineComponent({
		components: {
			StoriesEditor: StoriesEditor,
			ColibriPlusAttribution: ColibriPlusAttribution,
			Backdrop: Backdrop
		}
	});
</script>