import { computed, reactive, ref, watch } from 'vue';
import { colibriTranslator } from '@/kernel/services/translator/index.js';

export function useProfileBioTranslation(profileData, guard) {
    const state = reactive({
        isTranslating: false,
        isTranslated: false,
    });
    const translatedBio = ref('');
    const sourceLanguage = ref('');
    const translationVersion = ref(0);
    const translationTarget = computed(() => window.BackendEmbeds?.locale || 'en');

    const reset = function() {
        translationVersion.value++;
        translatedBio.value = '';
        sourceLanguage.value = '';
        state.isTranslated = false;
        state.isTranslating = false;
    };

    watch(() => [
        profileData.value?.id,
        profileData.value?.bio,
        translationTarget.value,
    ], reset);

    const toggleBioTranslation = async function() {
        if (!profileData.value?.bio || state.isTranslating || !guard()) return;

        if (translatedBio.value) {
            state.isTranslated = !state.isTranslated;
            return;
        }

        const version = translationVersion.value;
        const profileId = profileData.value.id;
        state.isTranslating = true;

        try {
            const translated = await colibriTranslator().translate(
                profileData.value.bio,
                'auto',
                translationTarget.value,
                null,
                profileId,
            );

            if (version === translationVersion.value && translated) {
                translatedBio.value = translated.text || translated;
                sourceLanguage.value = translated.sourceLanguage || '';
                state.isTranslated = true;
            }
        } finally {
            if (version === translationVersion.value) state.isTranslating = false;
        }
    };

    return {
        displayBio: computed(() => state.isTranslated ? translatedBio.value : profileData.value?.bio || ''),
        sourceLanguage,
        state,
        toggleBioTranslation,
    };
}
