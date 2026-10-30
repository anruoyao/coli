import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const colibriTranslator = function() {
    return {
        translate: async (text, source = 'auto', target = window.BackendEmbeds?.locale || 'en', postId = null, profileId = null) => {
            return await colibriAPI().translator().with({
                ...(postId
                    ? { post_id: postId }
                    : profileId
                        ? { profile_id: profileId, text: text }
                        : { text: text, source: source }),
                target: target
            }).sendTo('translate').then((response) => {
                const data = response.data.data;
                const sourceLanguage = data.source_language || '';
                let sourceLanguageName = sourceLanguage;

                if (sourceLanguage && typeof Intl.DisplayNames === 'function') {
                    try {
                        sourceLanguageName = new Intl.DisplayNames(
                            [window.BackendEmbeds?.locale || 'en'],
                            { type: 'language' },
                        ).of(sourceLanguage.replaceAll('_', '-')) || sourceLanguage;
                    } catch (error) {
                        sourceLanguageName = sourceLanguage;
                    }
                }

                return {
                    text: data.translated_text,
                    sourceLanguage: sourceLanguageName,
                };
            }).catch((error) => {
                const message = error.response?.data?.message || (
                    typeof __t === 'function'
                        ? __t('labels.translation_failed')
                        : 'Translation failed. Please try again.'
                );
                alert(message);

                return false;
            });
        }
    };
};

export { colibriTranslator };
