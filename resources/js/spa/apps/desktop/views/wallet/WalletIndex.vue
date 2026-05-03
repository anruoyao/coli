<template>
    <div class="my-top-offset block">
        <div class="mb-12">
            <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('wallet.wallet_page')"></PageTitle>
        </div>

        <div class="w-content 2xl:w-6/12">
            <div class="mb-8">
                <WalletOverview></WalletOverview>
            </div>
            <div class="block">
                <WalletTransactions></WalletTransactions>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, ref, defineAsyncComponent, reactive } from 'vue';
    
    import PageTitle from '@D/components/layout/PageTitle.vue';
    import WalletOverview from '@D/views/wallet/parts/WalletOverview.vue';
    import WalletTransactions from '@D/views/wallet/parts/WalletTransactions.vue';

    export default defineComponent({
        components: {
            PageTitle: PageTitle,
            WalletOverview: WalletOverview,
            WalletTransactions: WalletTransactions
        }
    });
</script>