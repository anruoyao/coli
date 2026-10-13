import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const colibriTranslator = function() {
    return {
        translate: async (text, source = 'auto', target = window.BackendEmbeds?.locale || 'en') => {
            return await colibriAPI().translator().with({
                text: text,
                source: source,
                target: target
            }).sendTo('translate').then((response) => {
                return response.data.data.translated_text;
            }).catch((error) => {
                const message = error.response?.data?.message;
                if (message) {
                    alert(message);
                }

                return false;
            });
        }
    };
};

export { colibriTranslator };
