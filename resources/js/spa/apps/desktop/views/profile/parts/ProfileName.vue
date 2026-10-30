<template>
	<div class="block">
		<div class="block">
			<h1 class="text-title-2 font-bold leading-none text-lab-pr">
				{{ profileData.name }} <VerificationBadge size="md" v-if="profileData.verified"></VerificationBadge>
			</h1>
			<p class="text-par-s text-lab-sc">
				<span class="inline-flex items-center gap-1">
					<span>@{{ profileData.username }}</span>
				</span>
			</p>
		</div>
		<div v-if="profileData.bio" class="mt-4 max-w-8/12">
			<p class="text-par-m text-lab-pr2" v-html="markdownRenderer(displayBio)"></p>
			<TextTranslateButton
				v-on:click="toggleBioTranslation"
				v-bind:disabled="state.isTranslating"
				v-bind:buttonText="state.isTranslating ? $t('labels.translating') : state.isTranslated ? (sourceLanguage ? $t('labels.translated_from', { language_name: sourceLanguage }) : $t('labels.show_untranslated')) : $t('dd.translate')"></TextTranslateButton>
		</div>
		<div v-if="profileData.website" class="mb-6">
			<a v-bind:href="profileData.website" target="_blank" class="text-brand-900 text-par-n font-medium hover:underline">
				{{ profileData.website }}
			</a>
		</div>
	</div>
</template>

<script>
	import { defineComponent, inject } from 'vue';
	import MarkdownParser from 'markdown-it';
	import TextTranslateButton from '@D/components/inter-ui/buttons/TextTranslateButton.vue';
	import { useAuthGate } from '@D/core/composables/useAuthGate.js';
	import { useProfileBioTranslation } from '@/kernel/vue/composables/profile-bio-translation/index.js';

	export default defineComponent({
		setup() {
			const profileData = inject('profileData');
			const { guard } = useAuthGate();
			const translation = useProfileBioTranslation(profileData, guard);
            const MarkdownIT = new MarkdownParser({
                html: true,
                breaks: true,
                linkify: true
            });

			return {
				profileData: profileData,
				displayBio: translation.displayBio,
				sourceLanguage: translation.sourceLanguage,
				state: translation.state,
				toggleBioTranslation: translation.toggleBioTranslation,
                markdownRenderer: (text) => {
                    return MarkdownIT.renderInline(text);
                }
			};
		},
		components: {
			TextTranslateButton,
		}
	});
</script>