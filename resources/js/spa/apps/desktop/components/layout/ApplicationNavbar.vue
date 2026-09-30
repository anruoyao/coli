<template>
    <div class="flex flex-col gap-3">
        <div class="block">
            <RouterLink v-bind:to="{ name: 'home_index' }" v-slot="{ isActive }" class="block">
                <div class="flex items-center" v-bind:class="[((isActive == true) ? 'sidenav-active' : 'sidenav-inactive')]">
                    <span class="size-icon-normal shrink-0">
                        <SvgIcon name="home-smile" v-bind:type="(isActive == true) ? 'solid' : 'line'"></SvgIcon>
                    </span>
                    <span class="ml-3 text-[19px]">
                        {{ $t('labels.home') }}
                    </span>
                </div>
            </RouterLink>
        </div>

        <!-- 登录用户：探索入口；访客：登录引导 -->
        <div class="block">
            <template v-if="! authStore.isGuest">
                <RouterLink v-bind:to="{ name: 'explore_posts' }" v-slot="{ isActive }" class="block">
                    <div class="flex items-center"  v-bind:class="[((isActive == true) ? 'sidenav-active' : 'sidenav-inactive')]">
                        <span class="size-icon-normal shrink-0">
                            <SvgIcon name="hash-02"></SvgIcon>
                        </span>
                        <span class="ml-3 text-[19px]">
                            {{ $t('labels.explore') }}
                        </span>
                    </div>
                </RouterLink>
            </template>
            <div v-else v-on:click="requestGate" class="flex items-center sidenav-inactive cursor-pointer">
                <span class="size-icon-normal shrink-0">
                    <SvgIcon name="hash-02"></SvgIcon>
                </span>
                <span class="ml-3 text-[19px]">
                    {{ $t('labels.explore') }}
                </span>
            </div>
        </div>
        <div class="block">
            <div v-on:click="openNotificationsModal" class="flex items-center sidenav-inactive cursor-pointer">
                <span class="size-icon-normal shrink-0">
                    <SvgIcon name="bell-01" type="line"></SvgIcon>
                </span>
                <span class="ml-3 text-[19px]">
                    {{ $t('labels.notifications') }}

                    <BadgeCounter v-if="!authStore.isGuest && notificationsCount.raw" v-bind:count="notificationsCount.formatted"></BadgeCounter>
                </span>
            </div>
        </div>
        <!-- 登录用户：消息入口；访客：登录引导 -->
        <div class="block">
            <template v-if="! authStore.isGuest">
                <RouterLink v-bind:to="{ name: 'messenger_index' }" v-slot="{ isActive }" class="block">
                    <div class="flex items-center"  v-bind:class="[((isActive == true) ? 'sidenav-active' : 'sidenav-inactive')]">
                        <span class="size-icon-normal shrink-0">
                            <SvgIcon name="message-chat-circle" v-bind:type="(isActive == true) ? 'solid' : 'line'"></SvgIcon>
                        </span>
                        <span class="ml-3 text-[19px]">
                            {{ $t('labels.messages') }}

                            <BadgeCounter v-if="inboxCount.raw" v-bind:count="inboxCount.formatted"></BadgeCounter>
                        </span>
                    </div>
                </RouterLink>
            </template>
            <div v-else v-on:click="requestGate" class="flex items-center sidenav-inactive cursor-pointer">
                <span class="size-icon-normal shrink-0">
                    <SvgIcon name="message-chat-circle" type="line"></SvgIcon>
                </span>
                <span class="ml-3 text-[19px]">
                    {{ $t('labels.messages') }}
                </span>
            </div>
        </div>
        <div class="block" v-if="$config('features.marketplace.enabled')">
            <RouterLink v-bind:to="{ name: 'marketplace_index' }" v-slot="{ isActive }" class="block">
                <div class="flex items-center" v-bind:class="[((isActive == true) ? 'sidenav-active' : 'sidenav-inactive')]">
                    <span class="size-icon-normal shrink-0">
                        <SvgIcon name="shopping-bag-03" v-bind:type="(isActive == true) ? 'solid' : 'line'"></SvgIcon>
                    </span>
                    <span class="ml-3 text-[19px]">
                        {{ $t('labels.marketplace') }}
                    </span>
                </div>
            </RouterLink>
        </div>
        <div class="block" v-if="$config('features.jobs.enabled')">
            <RouterLink v-bind:to="{ name: 'jobs_index' }" v-slot="{ isActive }" class="block">
                <div  class="flex items-center" v-bind:class="[((isActive == true) ? 'sidenav-active' : 'sidenav-inactive')]">
                    <span class="size-icon-normal shrink-0">
                        <SvgIcon name="briefcase-01" v-bind:type="(isActive == true) ? 'solid' : 'line'"></SvgIcon>
                    </span>
                    <span class="ml-3 text-[19px]">
                        {{ $t('labels.jobs') }}
                    </span>
                </div>
            </RouterLink>
        </div>
        
        <!-- 登录用户：我的资料；访客：登录引导 -->
        <div class="block">
            <template v-if="! authStore.isGuest">
                <RouterLink v-bind:to="{ name: 'profile_index', params: { id: userData.username } }" v-slot="{ isActive }" class="block">
                    <div  class="flex items-center sidenav-inactive">
                        <span class="size-icon-normal shrink-0">
                            <SvgIcon name="user-01" type="line"></SvgIcon>
                        </span>
                        <span class="ml-3 text-[19px]">
                            {{ $t('labels.my_profile') }}
                        </span>
                    </div>
                </RouterLink>
            </template>
            <div v-else v-on:click="requestGate" class="flex items-center sidenav-inactive cursor-pointer">
                <span class="size-icon-normal shrink-0">
                    <SvgIcon name="login-02" type="line"></SvgIcon>
                </span>
                <span class="ml-3 text-[19px]">
                    {{ $t('buttons.login') }}
                </span>
            </div>
        </div>
        <div class="block pl-icon-normal pr-6">
            <span class="block bg-bord-sc h-px mx-3"></span>
        </div>
        <div class="block">
            <NavbarDropdown></NavbarDropdown>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed, defineAsyncComponent, onMounted, onUnmounted } from 'vue';
    import { useAuthStore } from '@D/store/auth/auth.store.js';
    import { useNotificationsStore } from '@D/store/notifications/notifications.store.js';
    import { useInboxStore } from '@D/store/chats/inbox.store.js';
    import { colibriSounds } from '@/kernel/services/sounds/index.js';
    import { colibriEventBus } from '@/kernel/events/bus/index.js';

    import BadgeCounter from '@D/components/general/counters/BadgeCounter.vue';
    import BRD from '@/kernel/websockets/brd/index.js';

    export default defineComponent({
        setup: function() {
            const authStore = useAuthStore();
            const notificationsStore = useNotificationsStore();
            const inboxStore = useInboxStore();
            const notificationsCount = computed(() => {
                return notificationsStore.unreadCount;
            });

            const inboxCount = computed(() => {
                return inboxStore.unreadCount;
            });

            const requestGate = function() {
                colibriEventBus.emit('auth-gate:request', {});
            };

            onMounted(() => {
                // 访客：不抓取计数、不订阅私有频道
                if (authStore.isGuest) return;

                notificationsStore.fetchUnreadCount();

                inboxStore.fetchUnreadCount();

                if(window.ColibriBRD) {
                    ColibriBRD.private(BRD.getChannel('AUTH_USER', [authStore.userData.id])).notification(function (event) {
                        if(event.type === 'chat.notification') {
                            inboxStore.fetchUnreadCount();
                        }
                        else {
                            notificationsStore.setUnreadNotificationsCount(event.data);
                            colibriEventBus.emit('notifications:received');
                        }

                        if(localStorage.getItem('notificationsSound')) {
                            if(event.type === 'chat.notification') {
                                colibriSounds.backgroundChatMessageReceived();
                            }
                            else {
                                colibriSounds.notificationReceived();
                            }
                        }
                    });
                }
            });

            onUnmounted(() => {
                if (authStore.isGuest || ! window.ColibriBRD) return;

                ColibriBRD.leave(BRD.getChannel('AUTH_USER', [authStore.userData.id]));
            });

            return {
                authStore: authStore,
                requestGate: requestGate,
                notificationsCount: notificationsCount,
                inboxCount: inboxCount,
                userData: authStore.userData,
                openNotificationsModal: () => {
                    // 访客：登录引导
                    if (authStore.isGuest) {
                        requestGate();

                        return;
                    }

                    notificationsStore.openNotifications();
                }
            };
        },
        components: {
            NavbarDropdown: defineAsyncComponent(() => {
                return import('@D/components/layout/parts/navbar/NavbarDropdown.vue');
            }),
            BadgeCounter: BadgeCounter
        }
    });
</script>