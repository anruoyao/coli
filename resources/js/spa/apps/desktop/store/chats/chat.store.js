import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { useInboxStore } from '@D/store/chats/inbox.store.js';

const useChatStore = defineStore('chats_chat', {
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
		isDirect: function() {
			return this.chatData.type == 'direct';
		},
		hasDescription: function() {
			return this.chatData.chat_info.description.length > 0;
		},
		otherParticipants: function() {
			return this.chatData.relations.participants;
		}
	},
	actions: {
		fetchChatData: async function(chatId) {
			let state = this;

			await colibriAPI().messenger().getFrom(`chat/${chatId}`).then(function(response) {
				state.chatData = response.data.data;

				state.chatId = chatId;
			}).catch(function(error) {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		fetchChatParticipants: async function() {
			let state = this;

			await colibriAPI().messenger().getFrom(`chat/${state.chatId}/participants`).then(function(response) {
				state.chatParticipants = response.data.data;
			}).catch(function(error) {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		markMessagesAsRead: function() {
			let state = this;

			colibriAPI().messenger().getFrom(`chat/${state.chatId}/read`).catch(function(error) {
				alert(error.response.data.message);
			});
		},
		fetchChatMessages: async function() {
			let state = this;

			await colibriAPI().messenger().getFrom(`chat/${state.chatId}/messages`).then(function(response) {
				state.chatMessages = response.data.data;
			}).catch(function(error) {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		sendMessage: async function(messageData = {}) {
			let state = this;

			await colibriAPI().messenger().with({
				chat_id: state.chatId,
				...messageData
			}).sendTo('send').catch(function(error) {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		deleteMessage: async function(messageId, deleteForAll = false) {
			let state = this;

			await colibriAPI().messenger().with({
				message_id: messageId,
				payload: {
					delete_for_all: deleteForAll
				}
			}).delete('chat/message/delete').then((response) => {
				if(! response.data.data.is_global_delete) {
					let messageIndex = state.chatMessages.findIndex((item) => {
						return item.id == messageId;
					});
		
					if(messageIndex !== -1) {
						state.chatMessages.splice(messageIndex, 1);
					}
				}
			}).catch(function(error) {
				if(error.response) {
					alert(error.response.data.message);
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
		clearChatConversation: async function() {
			let state = this;

			await colibriAPI().messenger().delete(`chat/${state.chatId}/clear`).then(function(response) {
				state.chatMessages = [];
			}).catch(function(error) {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		deleteChat: async function() {
			let state = this;
			
			await colibriAPI().messenger().delete(`chat/${state.chatId}/delete`).then(function(response) {
				state.chatMessages = [];

				state.inboxStore.removeChatFromHistory(state.chatId);
			}).catch(function(error) {
				if(error.response) {
					throw new Error(error.response.data.message);
				}
			});
		},
		markMessageAsDeleted: async function(messageId) {
			let deletedMessage = this.chatMessages.find((item) => {
				return item.id == messageId;
			});

			if (deletedMessage) {
				deletedMessage.meta.is_deleted = true;
			}
		},
		addReaction: function(reactionId, messageId) {
			let state = this;
			
			colibriAPI().messenger().with({
				unified_id: reactionId,
				message_id: messageId
			}).sendTo('chat/message/add-reaction').then((response) => {
				let reactableMessage = state.chatMessages.find((item) => {
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
		updateLastReadMessageForParticipant: function(data) {
			let participantData = this.chatData.relations.participants.find((p) => {
				return data.user_id == p.user_id;
			});

			if(participantData) {
				participantData.last_read_message_id = data.last_read_message_id;
			}
		},
		appendMessage: function(messageData = {}) {
			let state = this;

			state.chatMessages.push(messageData);

			let chatData = state.inboxStore.chatsHistory.find((item) => {
				return messageData.chat_uuid == item.chat_id;
			});

			if(chatData) {
				chatData.last_message = messageData.content;
			}
		}
	}
});

export { useChatStore };