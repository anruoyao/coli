<template>
	<div class="block mb-4" v-if="profileData.bio || profileData.website">
		<div v-if="profileData.bio">
			<p class="text-par-n text-lab-pr2 mb-2" v-html="$mdInline(displayBio)"></p>
			<button
				class="inline-flex items-center text-lab-sc hover:text-brand-900"
				v-on:click="toggleBioTranslation"
				v-bind:disabled="state.isTranslating"
			>
				<span class="size-icon-x-small">
					<SvgIcon name="translate-01" type="line" classes="size-full"></SvgIcon>
				</span>
				<span class="text-cap-s ml-0.5 leading-none">
					{{ state.isTranslating ? $t('labels.translating') : state.isTranslated ? (sourceLanguage ? $t('labels.translated_from', { language_name: sourceLanguage }) : $t('labels.show_untranslated')) : $t('dd.translate') }}
				</span>
			</button>
		</div>
		<div v-if="profileData.website">
			<a v-bind:href="profileData.website" target="_blank" class="text-lab-pr text-par-m underline">
				{{ profileData.website }}
			</a>
		</div>
	</div>
</template>

<script>
	import { defineComponent, inject } from 'vue';
	import { useAuthGate } from '@M/core/composables/useAuthGate.js';
	import { useProfileBioTranslation } from '@/kernel/vue/composables/profile-bio-translation/index.js';

	export default defineComponent({
		setup: function() {
			const profileData = inject('profileData');
			const { guard } = useAuthGate();
			const translation = useProfileBioTranslation(profileData, guard);

			return {
				profileData: profileData,
				displayBio: translation.displayBio,
				sourceLanguage: translation.sourceLanguage,
				state: translation.state,
				toggleBioTranslation: translation.toggleBioTranslation
			};
		}
	});
</script>
