import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

import { useInboxStore } from '@M/store/chats/inbox.store.js';

const useChatStore = defineStore('mobile_chats_chat', {
	state: () => {
		return {
			chatId: null,
			chatData: {},
			chatMessages: [],
			chatParticipants: [],
			inboxStore: useInboxStore()
		};
	},
	getters: {
		otherParticipants: function() {
			return this.chatData.relations.participants;
		}
	},
	actions: {
		fetchChatData: async function(chatId) {
			await colibriAPI().messenger().getFrom(`chat/${chatId}`).then((response) => {
				this.chatData = response.data.data;

				this.chatId = chatId;
			}).catch((error) => {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		fetchChatParticipants: async function() {
			await colibriAPI().messenger().getFrom(`chat/${this.chatId}/participants`).then((response) => {
				this.chatParticipants = response.data.data;
			}).catch((error) => {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		fetchChatMessages: async function() {
			await colibriAPI().messenger().getFrom(`chat/${this.chatId}/messages`).then((response) => {
				this.chatMessages = response.data.data;
			}).catch((error) => {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		deleteMessage: async function(messageId, deleteForAll = false) {
			await colibriAPI().messenger().with({
				message_id: messageId,
				payload: {
					delete_for_all: deleteForAll
				}
			}).delete('chat/message/delete').then((response) => {
				if(! response.data.data.is_global_delete) {
					let messageIndex = this.chatMessages.findIndex((item) => {
						return item.id == messageId;
					});
		
					if(messageIndex !== -1) {
						this.chatMessages.splice(messageIndex, 1);
					}
				}
			}).catch(function(error) {
				if(error.response) {
					alert(error.response.data.message);
				}
			});
		},
		sendMessage: async function(messageData = {}) {
			await colibriAPI().messenger().with({
				chat_id: this.chatId,
				...messageData
			}).sendTo('send').catch(function(error) {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		appendMessage: function(messageData = {}) {
			this.chatMessages.push(messageData);

			let chatData = this.inboxStore.chatsHistory.find((item) => {
				return messageData.chat_uuid == item.chat_id;
			});

			if(chatData) {
				chatData.last_message = messageData.content;
			}
		},
		markMessageAsDeleted: async function(messageId) {
			let deletedMessage = this.chatMessages.find((item) => {
				return item.id == messageId;
			});

			if (deletedMessage) {
				deletedMessage.meta.is_deleted = true;
			}
		},
		updateLastReadMessageForParticipant: function(data) {
			this.otherParticipants.forEach((participant) => {
				participant.last_read_message_id = data.last_read_message_id;
			});
		},
		markMessagesAsRead: function() {
			colibriAPI().messenger().getFrom(`chat/${this.chatId}/read`).catch(function(error) {
				alert(error.response.data.message);
			});
		},
		addReaction: function(reactionId, messageId) {
			colibriAPI().messenger().with({
				unified_id: reactionId,
				message_id: messageId
			}).sendTo('chat/message/add-reaction').then((response) => {
				let reactableMessage = this.chatMessages.find((item) => {
					return item.id == messageId;
				});

				if (reactableMessage) {
					reactableMessage.relations.reactions = response.data.data;
				}
				
			}).catch((error) => {
				if (error.response) {
					alert(error.response.data.message);
				}
			});
		},
		clearChat: async function() {
			await colibriAPI().messenger().delete(`chat/${this.chatId}/clear`).then((response) => {
				this.chatMessages = [];
			}).catch((error) => {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		deleteChat: async function() {
			await colibriAPI().messenger().delete(`chat/${this.chatId}/delete`).then((response) => {
				this.chatMessages = [];

				this.inboxStore.removeChatFromHistory(this.chatId);
			}).catch(function(error) {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		archiveChat: async function(chatId) {
			await colibriAPI().messenger().delete(`chat/${chatId}/archive`).then((response) => {
				this.inboxStore.removeChatFromHistory(chatId);
			}).catch(function(error) {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		unarchiveChat: async function(chatId) {
			await colibriAPI().messenger().delete(`chat/${chatId}/unarchive`).then((response) => {
				this.inboxStore.fetchChatsHistory();
			}).catch(function(error) {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
	}
});

export { useChatStore };