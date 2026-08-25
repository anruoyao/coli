<template>
    <template v-if="appLoading">
        <div class="flex-center w-screen h-screen relative">
            <span class="absolute top-6 left-6 text-par-m text-lab-pr">{{ $t('labels.hi_there') }}</span>
            <span class="absolute top-6 right-6 text-par-m text-lab-pr">{{ $t('labels.one_moment') }}...</span>
            <div class="inline-block w-16">
                <img v-bind:src="$embedder('assets.logos.url')" alt="Logo" class="w-full">
            </div>
        </div>
    </template>
    <template v-else>
        <ApplicationMainLayout v-if="isMainLayout"></ApplicationMainLayout>

        <ApplicationMessengerLayout v-else-if="isMessengerLayout"></ApplicationMessengerLayout>

        <ApplicationFlatLayout v-else-if="isFlatLayout"></ApplicationFlatLayout>

        <ApplicationStoriesLayout v-else-if="isStoriesLayout"></ApplicationStoriesLayout>

        <ApplicationInfoLayout v-else-if="isInfoLayout"></ApplicationInfoLayout> 
    </template>

    <NetworkStatusBar></NetworkStatusBar>

    <MaintenanceOverlay v-if="appStore.maintenance && appStore.maintenance.on" :message="appStore.maintenance.message" :until="appStore.maintenance.until"></MaintenanceOverlay>
</template>

<script>
    import { defineComponent, computed, onMounted, onUnmounted, ref, defineAsyncComponent } from 'vue';
    import { useRoute } from 'vue-router';
    import { useAppStore } from '@D/store/app/app.store.js';

    import { Layouts } from '@D/core/constants/layouts.js';
    
    import ApplicationMainLayout from '@D/layouts/ApplicationMainLayout.vue';
    import NetworkStatusBar from '@D/components/layout/parts/network/NetworkStatusBar.vue';
    import MaintenanceOverlay from '@D/components/layout/parts/maintenance/MaintenanceOverlay.vue';

    // 公共命令频道：维护模式等全局指令广播（与 App 端一致）
    const PUBLIC_COMMAND_CHANNEL = 'App.Commands';
    const MAINTENANCE_EVENT = '.main.command';
    
    export default defineComponent({
        setup: function(_, context) {
            const appLoading = ref(true);
            const route = useRoute();
            const appStore = useAppStore();

            const layoutType = computed(() => {
                return route.meta.layout;
            });

            window.userInteracted = false;

            const handleUserInteraction = () => {
                window.userInteracted = true;
                removeInteractionListeners();
            };

            const removeInteractionListeners = () => {
                window.removeEventListener('click', handleUserInteraction);
                window.removeEventListener('keydown', handleUserInteraction);
                window.removeEventListener('mousemove', handleUserInteraction);
                window.removeEventListener('touchstart', handleUserInteraction);
            };

            const setupInteractionListeners = () => {
                window.addEventListener('click', handleUserInteraction);
                window.addEventListener('keydown', handleUserInteraction);
                window.addEventListener('mousemove', handleUserInteraction);
                window.addEventListener('touchstart', handleUserInteraction);
            };

            // 订阅公共命令频道：维护模式实时开/关（开维护即时盖遮罩，关维护即时恢复）
            const maintenanceSubscription = () => {
                if(!window.ColibriBRD) return;

                ColibriBRD.channel(PUBLIC_COMMAND_CHANNEL).listen(MAINTENANCE_EVENT, function(event) {
                    if(event && event.action === 'maintenance_on') {
                        appStore.setMaintenance({
                            on: true,
                            message: event.message,
                            until: event.until
                        });
                    }
                    else if(event && event.action === 'maintenance_off') {
                        appStore.setMaintenance({ on: false });
                    }
                });
            };

            const cancelMaintenanceSubscription = () => {
                if(!window.ColibriBRD) return;

                ColibriBRD.leave(PUBLIC_COMMAND_CHANNEL);
            };

            onMounted(async () => {
                await appStore.bootstrapApplication();
                
                setTimeout(() => {
                    appLoading.value = false;
                }, 500);

                setupInteractionListeners();

                maintenanceSubscription();
            });

            onUnmounted(() => {
                removeInteractionListeners();

                cancelMaintenanceSubscription();
            });

            return {
                appLoading: appLoading,
                appStore: appStore,
                isMainLayout: computed(() => {
                    return layoutType.value == Layouts.MAIN;
                }),
                isStoriesLayout: computed(() => {
                    return layoutType.value == Layouts.STORIES;
                }),
                isInfoLayout: computed(() => {
                    return layoutType.value == Layouts.INFO;
                }),
                isFlatLayout: computed(() => {
                    return layoutType.value == Layouts.FLAT;
                }),
                isMessengerLayout: computed(() => {
                    return layoutType.value == Layouts.MESSENGER;
                })
            }
        },
        components: {
            MaintenanceOverlay: MaintenanceOverlay,
            NetworkStatusBar: NetworkStatusBar,
            ApplicationMainLayout: ApplicationMainLayout,
            ApplicationStoriesLayout: defineAsyncComponent(() => {
                return import('@D/layouts/ApplicationStoriesLayout.vue');
            }),
            ApplicationInfoLayout: defineAsyncComponent(() => {
                return import('@D/layouts/ApplicationInfoLayout.vue');
            }),
            ApplicationFlatLayout: defineAsyncComponent(() => {
                return import('@D/layouts/ApplicationFlatLayout.vue');
            }),
            ApplicationMessengerLayout: defineAsyncComponent(() => {
                return import('@D/layouts/ApplicationMessengerLayout.vue');
            })
        }
    });
</script>