<template>
	<div class="px-4 pt-4">
		<QuickSearch v-model="searchQuery" v-on:cancel="cancelSearch" v-bind:placeholder="searchPlaceholder"></QuickSearch>
	</div>
	<div v-if="state.isLoading">
		<ChatItemSkeleton v-for="i in 7"></ChatItemSkeleton>
	</div>
	<template v-else-if="isEmptyInbox">
		<div class="py-16 text-center">
			<p class="text-par-s text-lab-sc">
				{{  $t('chat.no_chat_history') }}
			</p>
		</div>
	</template>
	<template v-else>
		<template v-if="isSearching">
			<ChatItem v-if="searchResults.length > 0" v-for="chatData in searchResults" v-bind:chatData="chatData" v-bind:key="chatData.chat_id"></ChatItem>
			<div v-else class="py-16 text-center">
				<p class="text-par-s text-lab-sc">
					{{  $t('chat.no_chats_found') }}
				</p>
			</div>
		</template>
		<template v-else>
			<ChatItem v-for="chatData in chatsHistory" v-bind:chatData="chatData" v-bind:key="chatData.chat_id"></ChatItem>
		</template>
	</template>
</template>

<script>
	import { defineComponent, reactive, watch, onMounted, ref, computed } from 'vue';
	import { useInboxStore } from '@M/store/chats/inbox.store.js';

	import ChatItemSkeleton from '@M/views/messenger/children/inbox/parts/ChatItemSkeleton.vue';
	import ChatItem from '@M/views/messenger/children/inbox/parts/ChatItem.vue';
	import QuickSearch from '@M/components/general/search/QuickSearch.vue';

	export default defineComponent({
		props: {
			historyType: {
				type: String,
				default: 'chats'
			}
		},
		setup: function(props) {
			const searchQuery = ref('');
			const searchResults = ref([]);

			const inboxStore = useInboxStore();

			const state = reactive({
				isLoading: true
			});

			const chatsHistory = computed(() => {
                if(props.historyType == 'groups') {
					return inboxStore.chatsHistory.filter((chatItem) => {
						return chatItem.type == 'group';
					});
				}
				
				return inboxStore.chatsHistory;
            });

			watch(searchQuery, (queryValue) => {
                searchResults.value = chatsHistory.value.filter((item) => {
                    if(item.chat_info.name.toLowerCase().includes(queryValue.toLowerCase())) {
                        return true;
                    }
                });
            });

			onMounted(async () => {
                if(inboxStore.chatsHistory.length === 0) {
                    await inboxStore.fetchChatsHistory();
                }

                state.isLoading = false;
            });
			
			return {
				searchQuery: searchQuery,
				state: state,
				chatsHistory: chatsHistory,
				searchResults: searchResults,
				isSearching: computed(() => {
					return searchQuery.value.length > 0;
				}),
				isEmptyInbox: computed(() => {
                    return chatsHistory.value.length === 0;
                }),
				cancelSearch: () => {
					searchQuery.value = '';

					searchResults.value = [];
				},
				searchPlaceholder: computed(() => {
					if(state.isLoading) {
						return `${__t('labels.loading')}...`;
					}

					return __t('chat.search');
				})
			};
		},
		components: {
			ChatItemSkeleton: ChatItemSkeleton,
			ChatItem: ChatItem,
			QuickSearch: QuickSearch
		}
	});
</script>