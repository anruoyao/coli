<template>
    <SidebarContainer>
        <template v-slot:sidebarTitle>
            <div class="px-4 mb-4">
                <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('chat.tabs.archived')"></PageTitle>
            </div>
            <div v-if="! isWSEstablished" class="mb-4">
                <WSConnectionAlert></WSConnectionAlert>
            </div>
        </template>

        <template v-slot:sidebarBody>
			<ChatsArchive></ChatsArchive>
        </template>
    </SidebarContainer>
</template>

<script>
    import { defineComponent, reactive, computed, onMounted } from 'vue';
    import { useInboxStore } from '@D/store/chats/inbox.store.js';

    import PageTitle from '@D/components/layout/PageTitle.vue';
    import SidebarContainer from '@D/components/general/contextual-sidebar/SidebarContainer.vue';
    import WSConnectionAlert from '@D/views/messenger/history/parts/WSConnectionAlert.vue';
    import ChatsArchive from '@D/views/messenger/history/parts/ChatsArchive.vue';

    export default defineComponent({
        setup: function() {

            const inboxStore = useInboxStore();

            return {
                isWSEstablished: computed(() => {
                    return (window.ColibriBRConnected !== false);
                }),
            }
        },
        components: {
            PageTitle: PageTitle,
            SidebarContainer: SidebarContainer,
            WSConnectionAlert: WSConnectionAlert,
            ChatsArchive: ChatsArchive
        }
    });
</script>