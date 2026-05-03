<template>
    <ContentModal v-on:close="$emit('close')">
        <ModalHeader v-bind:modalTitle="$t('wallet.deposit_money')"></ModalHeader>
        <div v-if="state.isLoading">
            <LoadingSkeleton></LoadingSkeleton>
        </div>
        <div v-else class="block">
            <form v-on:submit.prevent="submitForm">
                <div class="block p-4">
                    <div class="mb-4">
                        <ModalTextInput 
                            v-bind:labelText="$t('wallet.deposit_amount')"
                            v-bind:placeholder="`0, 00 ${walletCurrency.symbol}`"
                            v-model="formData.amount"
                            v-bind:name="$t('wallet.deposit_amount')"
                            inputType="number"
                            v-bind:inputErrors="state.formErrors"
                            v-on:clear="formData.amount = ''"
                            v-bind:textLength="$embedder('config.wallet.deposit.max_amount')"
                        v-bind:hasFeedback="true">
                            <template v-slot:feedbackInfo>
                                {{ $t('wallet.deposit_amount_helper') }}
                            </template>
                        </ModalTextInput>
                    </div>
                    <div class="grid grid-cols-8 gap-2 mb-12">
                        <ProviderCircleCard
                            v-for="provider in paymentProviders"
                            v-bind:isActive="formData.provider === provider.id"
                            v-bind:key="provider.id"
                            v-bind:providerName="provider.name"
                            v-on:click="formData.provider = provider.id"
                        v-bind:logoUrl="provider.logo"></ProviderCircleCard>
                    </div>
                    <div class="block">
                        <div class="mb-2">
                            <PrimaryPillButton 
                                buttonType="submit" 
                                buttonSize="lm"
                                v-bind:disabled="! isValidForm"
                                v-bind:loading="state.isSubmitting"
                            v-bind:buttonText="$t('labels.continue')"></PrimaryPillButton>
                        </div>
                        <p class="text-par-s text-lab-sc" v-html="$t('wallet.tos_agree', { tos_link: $getRoute('terms_of_use') })"></p>
                    </div>
                </div>
            </form>
        </div>
    </ContentModal>
</template>

<script>
    import { defineComponent, reactive, computed, onMounted } from 'vue';
    import { useWalletStore } from '@D/store/wallet/wallet.store.js';

    import ContentModal from '@D/components/general/modals/ContentModal.vue';
    import ModalHeader from '@D/components/general/modals/parts/ModalHeader.vue';
    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
    import LoadingSkeleton from '@D/views/wallet/parts/LoadingSkeleton.vue';
    import ModalTextInput from '@D/components/forms/modal/ModalTextInput.vue';
    import ProviderCircleCard from '@D/components/general/payments/ProviderCircleCard.vue';

    export default defineComponent({
        emits: ['close'],
        setup: function() {
            const state = reactive({
                isLoading: true,
                isSubmitting: false,
                formErrors: []
            });

            const formData = reactive({
                amount: '',
                provider: ''
            });

            const walletStore = useWalletStore();
            const paymentProviders = computed(() => {
                return walletStore.paymentProviders;
            });

            onMounted(async () => {
                await walletStore.fetchPaymentProviders();
                state.isLoading = false;
            });

            return {
                state: state,
                paymentProviders: paymentProviders,
                formData: formData,
                walletCurrency: walletStore.walletData.currency,
                isValidForm: computed(() => {
                    if (! formData.amount || ! formData.provider) {
                        return false;
                    }

                    return true;
                }),
                submitForm: async () => {
                    if(! state.isSubmitting) {
                        state.isSubmitting = true;

                        await walletStore.createDepositPayment(formData).then((response) => {
                            if(response.data.data.is_hosted_checkout) {
                                window.location.href = response.data.data.checkout_url;
                            }
                        }).catch((error) => {
                            if(error.response) {
                                if(error.response.data.errors.amount) {
                                    state.formErrors = error.response.data.errors.amount;
                                }

                                else if(error.response.data.errors.provider) {
                                    state.formErrors = error.response.data.errors.provider;
                                }
                            }
                        });

                        state.isSubmitting = false;
                    }
                }
            }
        },
        components: {
            ContentModal: ContentModal,
            PrimaryPillButton: PrimaryPillButton,
            ModalHeader: ModalHeader,
            LoadingSkeleton: LoadingSkeleton,
            ModalTextInput: ModalTextInput,
            ProviderCircleCard: ProviderCircleCard
        }
    });
</script>