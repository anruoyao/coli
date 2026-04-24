import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const useInboxStore = defineStore('mobile_chats_inbox', {
    state: () => {
        return {
            chatsHistory: [],
            chatsArchive: [],
            chatRequests: [],
            chatRequestsCount: 0,
            unreadCount: {
				formatted: 0,
				raw: 0
			}
        };
    },
    actions: {
        fetchChatRequests: async function() {
            await colibriAPI().messenger().getFrom('chats/requests').then((response) => {
                this.chatRequests = response.data.data;
            }).catch((error) => {
                this.chatRequests = [];
            });
        },
        fetchChatRequestsCount: async function() {
            await colibriAPI().messenger().getFrom('chats/requests/count').then((response) => {
                this.chatRequestsCount = response.data.data.count;
            }).catch((error) => {
                this.chatRequestsCount = 0;
            });
        },
        fetchChatsHistory: async function() {
            await colibriAPI().messenger().getFrom('chats').then((response) => {
                this.chatsHistory = response.data.data;
            }).catch((error) => {
                this.chatsHistory = [];
            });
        },
        fetchUnreadCount: function() {
			colibriAPI().messenger().getFrom('unread/count').then((response) => {
				this.unreadCount = response.data.data;
			}).catch(() => {
				this.unreadCount = {
					formatted: 0,
					raw: 0
				};
			});
		},
        removeChatFromHistory: function(chatId) {
            let chatIndex = this.chatsHistory.findIndex((item) => {
                return item.chat_id == chatId;
            });

            if(chatIndex !== -1) {
                this.chatsHistory.splice(chatIndex, 1);
            }
        },
        fetchChatsArchive: async function() {
            await colibriAPI().messenger().getFrom('archive').then((response) => {
                this.chatsArchive = response.data.data;
            }).catch((error) => {
                this.chatsArchive = [];
            });
        }
    }
});

export { useInboxStore };