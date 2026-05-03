<template>
	<div v-if="state.isLoading" class="block">
		<ChatItemSkeleton v-for="i in 7"></ChatItemSkeleton>
	</div>
	<div v-else class="block">
		<template v-if="isEmptyInbox">
			<div class="py-24 text-center">
				<p class="text-par-s text-lab-sc">
					{{  $t('chat.no_chat_history') }}
				</p>
			</div>
		</template>
		<template v-else>
			<div class="px-4 mb-4">
				<QuickSearch v-on:cancel="handleSearchCancel" v-model.lazy="chatsSearchQuery" v-bind:placeholder="$t('chat.search')"></QuickSearch>
			</div>
			<template v-if="isSearching">
				<ChatItem v-if="searchResults.length" v-for="chatData in searchResults" v-bind:chatData="chatData" v-bind:key="chatData.chat_id"></ChatItem>

				<div v-else class="py-24 text-center">
					<p class="text-par-s text-lab-sc">
						{{  $t('chat.no_chats_found') }}
					</p>
				</div>
			</template>
			<template v-else>
				<ChatItem v-for="chatData in chatsHistory" v-bind:chatData="chatData"></ChatItem>
			</template>
		</template>
	</div>
</template>

<script>
    import { defineComponent, onMounted, ref, computed, reactive, watch } from 'vue';
    
    import { useInboxStore } from '@D/store/chats/inbox.store.js';

    import ChatItemSkeleton from '@D/views/messenger/history/parts/ChatItemSkeleton.vue';
    import ChatItem from '@D/views/messenger/history/parts/ChatItem.vue';
    import QuickSearch from '@D/components/general/search/QuickSearch.vue';

    export default defineComponent({
		props: {
			historyType: {
				type: String,
				default: 'chats'
			}
		},
        setup: function(props) {
            const state = reactive({
                isLoading: false
            });

            const chatsSearchQuery = ref('');
            const inboxStore = useInboxStore();
            const searchResults = ref([]);
            const chatsHistory = computed(() => {
                if(props.historyType == 'groups') {
					return inboxStore.chatsHistory.filter((chatItem) => {
						return chatItem.type === 'group';
					});
				}
				
				return inboxStore.chatsHistory;
            });
            
            const isSearching = computed(() => {
                return chatsSearchQuery.value.length > 0;
            });

            watch(chatsSearchQuery, (queryValue) => {
                searchResults.value = chatsHistory.value.filter((item) => {
                    if(item.chat_info.name.toLowerCase().includes(queryValue.toLowerCase())) {
                        return true;
                    }
                });
            });

            onMounted(async () => {
                state.isLoading = true;

                if(! inboxStore.chatsHistory.length) {
                    await inboxStore.fetchChatsHistory();
                }

                state.isLoading = false;
            });

            return {
                state: state,
                isSearching: isSearching,
                chatsHistory: chatsHistory,
                searchResults: searchResults,
                chatsSearchQuery: chatsSearchQuery,
                isEmptyInbox: computed(() => {
                    return chatsHistory.value.length === 0;
                }),
                handleSearchCancel: () => {
                    chatsSearchQuery.value = '';
                    searchResults.value = [];
                }
            }
        },
        components: {
            ChatItem: ChatItem,
            ChatItemSkeleton: ChatItemSkeleton,
            QuickSearch: QuickSearch
        }
    });
</script>