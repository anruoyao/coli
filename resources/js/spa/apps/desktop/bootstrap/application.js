import '@D/bootstrap/initialization/index.js';

import { createApp, defineAsyncComponent } from 'vue';
import { createI18n } from 'vue-i18n';
import { createPinia } from 'pinia';
import { postDeleteListener } from '@/kernel/vue/plugins/pinia/post/delete-listener.js';

import outsideClickDirective from '@/kernel/vue/directives/click.outside.js';

import Router from '@D/router/index.js';
import LanguageMessages from '@/lang/index.js';

import ColibriPlusDesktop from '@D/bootstrap/boot/ColibriPlusDesktop.vue';
import PrimeVue from 'primevue/config';
import globalProperties from '@/kernel/vue/plugins/global.properties.js';

const Application = createApp(ColibriPlusDesktop);

async function initializeI18n() {
    const messages = await LanguageMessages.messages();

    return createI18n({
        locale: LanguageMessages.langLocale,
        warnHtmlInMessage: false,
        warnHtmlMessage: false,
        legacy: false,
        fallbackLocale: LanguageMessages.langLocale,
        messages: {
            [BackendEmbeds.locale]: messages
        }
    });
}

const ColibriPlusI18n = await initializeI18n();
window.__t = ColibriPlusI18n.global.t;

const PiniaInstance = createPinia();

PiniaInstance.use(postDeleteListener);

Application.use(PiniaInstance);

Application.directive('outside-click', outsideClickDirective);
Application.use(globalProperties);
Application.use(Router);
Application.use(PrimeVue, {
    unstyled: true
});

Application.use(ColibriPlusI18n);

Application.component('Border', defineAsyncComponent(() => {
    return import("@/kernel/vue/components/general/Border.vue");
}));

Application.component('VerificationBadge', defineAsyncComponent(() => {
    return import("@/kernel/vue/components/general/badges/VerificationBadge.vue");
}));

Application.component('SvgIcon', defineAsyncComponent(() => {
    return import("@/kernel/vue/components/icons/SvgIcon.vue");
}));

Application.component('TimeAgo', defineAsyncComponent(() => {
    return import("@/kernel/vue/components/general/date-time/TimeAgo.vue");
}));


Application.component('FileFormatIcon', defineAsyncComponent(() => {
    return import("@/kernel/vue/components/icons/FileFormatIcon.vue");
}));

Application.component('PrimaryTransition', defineAsyncComponent(() => {
    return import("@D/components/general/transitions/PrimaryTransition.vue");
}));

Application.component('PrimaryDotsAnimation', defineAsyncComponent(() => {
    return import("@D/components/general/animations/PrimaryDotsAnimation.vue");
}));

Application.component('PrimarySpinAnimation', defineAsyncComponent(() => {
    return import("@D/components/general/animations/PrimarySpinAnimation.vue");
}));

Application.mount("#colibriplus-desktop-app");