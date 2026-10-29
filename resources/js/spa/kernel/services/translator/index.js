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
                return response.data.data.translated_text;
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
