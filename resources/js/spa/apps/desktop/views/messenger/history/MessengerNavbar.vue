<template>
    <SidebarContainer>
        <template v-slot:sidebarTitle>
            <div class="px-4 mb-4">
                <PageTitle v-bind:hasBack="true" v-bind:backHome="true" v-bind:titleText="$t('labels.messages')"></PageTitle>
            </div>

            <div v-if="! isWSEstablished">
                <WSConnectionAlert></WSConnectionAlert>
            </div>
        </template>

        <template v-slot:sidebarBody>
            <div class="border-b border-b-bord-pr mb-4">
                <ContentTabs>
                    <TabsButton v-on:click="state.activeTab = 'chats'" v-bind:isActive="state.activeTab === 'chats'">
                        {{ $t('chat.tabs.chats') }}
                    </TabsButton>
                    <TabsButton v-on:click="state.activeTab = 'groups'" v-bind:isActive="state.activeTab === 'groups'">
                        {{ $t('chat.tabs.groups') }}
                    </TabsButton>
                    <TabsButton v-on:click="state.activeTab = 'requests'" v-bind:isActive="state.activeTab === 'requests'">
                        {{ $t('chat.tabs.requests') }} <template v-if="requestsCount">({{ requestsCount }})</template>
                    </TabsButton>
                </ContentTabs>
            </div>
            <template v-if="state.activeTab === 'chats' || state.activeTab === 'groups'">
                <ChatsHistory v-bind:historyType="state.activeTab" v-bind:key="state.activeTab"></ChatsHistory>
            </template>
            <template v-else-if="state.activeTab === 'requests'">
                <ChatRequests></ChatRequests>
            </template>
        </template>
    </SidebarContainer>
</template>

<script>
    import { defineComponent, reactive, computed, onMounted } from 'vue';
    import { useInboxStore } from '@D/store/chats/inbox.store.js';

    import PageTitle from '@D/components/layout/PageTitle.vue';
    import SidebarContainer from '@D/components/general/contextual-sidebar/SidebarContainer.vue';
    import WSConnectionAlert from '@D/views/messenger/history/parts/WSConnectionAlert.vue';
    import ContentTabs from '@D/components/general/tabs/content/ContentTabs.vue';
    import TabsButton from '@D/components/general/tabs/content/parts/TabsButton.vue';
    import ChatsHistory from '@D/views/messenger/history/parts/ChatsHistory.vue';
    import ChatRequests from '@D/views/messenger/history/parts/ChatRequests.vue';

    export default defineComponent({
        setup: function() {

            const inboxStore = useInboxStore();

            onMounted(() => {
                inboxStore.fetchChatRequestsCount();
            });

            return {
                requestsCount: computed(() => {
                    return inboxStore.chatRequestsCount;
                }),
                state: reactive({
                    activeTab: 'chats'
                }),
                isWSEstablished: computed(() => {
                    return (window.ColibriBRConnected !== false);
                }),
            }
        },
        components: {
            PageTitle: PageTitle,
            SidebarContainer: SidebarContainer,
            WSConnectionAlert: WSConnectionAlert,
            ContentTabs: ContentTabs,
            ChatsHistory: ChatsHistory,
            ChatRequests: ChatRequests,
            TabsButton: TabsButton
        }
    });
</script>