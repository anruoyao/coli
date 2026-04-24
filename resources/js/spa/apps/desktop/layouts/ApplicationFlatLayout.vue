<template>
    <RouterView></RouterView>

    <ToastNotification></ToastNotification>
    <AccountSwitcherModal></AccountSwitcherModal>
</template>

<script>
    import { defineComponent } from 'vue';

    import ToastNotification from '@D/components/notifications/toast/ToastNotification.vue';
    import AccountSwitcherModal from '@D/components/accounts/AccountSwitcherModal.vue';

    export default defineComponent({
        components: {
            ToastNotification: ToastNotification,
            AccountSwitcherModal: AccountSwitcherModal
        }
    });
</script>