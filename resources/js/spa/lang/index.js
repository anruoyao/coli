import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

export default {
    langLocale: BackendEmbeds.locale,
    messages: async function () {
        try {
            return await colibriAPI().translations().params({
                locale: this.langLocale
            }).getFrom('app').then((response) => {
                return response.data.data;
            });
        } catch (error) {
            console.error(`Could not load messages for locale: ${this.langLocale}`, error);
        }
    }
}
