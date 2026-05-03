<template>
	<div class="px-4 py-0.5" v-on:click="showMenuButton" v-on:dblclick.stop="reply" v-bind:title="$t('chat.double_click_to_reply')">
		<div class="flex-1 group">
			<div class="flex gap-2.5 items-end" v-bind:class="{ 'flex-row-reverse': isSender }">
				<div v-if="! isSender" class="shrink-0">
					<Avatar2ExtraSmall v-bind:avatarSrc="messageUser.avatar_url"></Avatar2ExtraSmall>
				</div>
				<div class="overflow-hidden">
					<div class="flex" v-bind:class="{ 'flex-row-reverse': isSender }">
						<div class="overflow-hidden flex-1 rounded-xl p-1 w-fit min-w-3/12 max-w-80"
						v-bind:class="[isSender ? 'bg-fill-qt border border-fill-fv' : 'border border-bord-pr']">

							<template v-if="hasLinkSnapshot">
								<div class="w-full mb-1">
									<LinkSnapshot v-bind:linkSnapshot="messageData.relations.link_snapshot"></LinkSnapshot>
								</div>
							</template>

							<template v-if="messageData.has_parent">
								<div class="mb-1">
									<ChatMessageReply v-bind:replyData="replyData"></ChatMessageReply>
								</div>
							</template>
							<div class="px-1">
								<p class="text-par-l text-lab-pr">
									<template v-if="isNotDeleted">
										<span v-html="$mdInline(messageData.content)"></span>
									</template>
									<template v-else>
										<span class="text-lab-sc text-par-m">
											{{ $t('chat.message_is_deleted') }}
										</span>
									</template>
								</p>
							</div>
							<div class="leading-none flex items-center justify-end px-1">
								<time class="text-cap-l text-lab-sc mr-1">{{ messageData.date.time_ago }}</time>
								<template v-if="isSender">
									<span v-if="isMessageSeen" class="size-4 text-brand-900">
										<SvgIcon type="line" name="message-double-check"></SvgIcon>
									</span>	
									<span v-else class="size-4 text-lab-sc">
										<SvgIcon type="line" name="message-check"></SvgIcon>
									</span>
								</template>
							</div>
						</div>
					</div>
				</div>
				<div v-if="isNotDeleted" class="shrink-0 w-x-small-avatar self-center">
					<DropdownButton v-if="state.menuButton" v-on:click="state.mainMenu.open"></DropdownButton>
				</div>
			</div>
			<div class="block mt-1" v-if="isNotDeleted && hasReactions">
				<div class="flex" v-bind:class="[isSender ? 'flex-row-reverse' : '']">
					<div class="ml-2x-small-avatar pl-2.5">
						<ReactionsViewer v-on:add="addReaction" v-bind:reactions="messageData.relations.reactions"></ReactionsViewer>
					</div>
				</div>
			</div>
		</div>
	</div>

	<ActionSheet v-if="isNotDeleted && state.mainMenu.status" v-on:close="state.mainMenu.close" v-bind:isMuted="true">
		<div v-on:click.stop="state.mainMenu.close">
			<div class="mb-4">
				<ActionSheetReactions v-on:add="addReaction"></ActionSheetReactions>
			</div>
			<ActionSheetGroup>
				<ActionSheetItem v-on:click="state.reactionMenu.open" iconName="heart-rounded" v-bind:textLabel="$t('dd.add_reaction')"></ActionSheetItem>
				
				<ActionSheetItem v-on:click="reply" iconName="pencil-line" v-bind:textLabel="$t('dd.message.reply', { name: messageUser.name })"></ActionSheetItem>

				<ActionSheetItem v-on:click="deleteMessage" itemColor="text-red-900" iconName="trash-04" v-bind:textLabel="$t('dd.message.delete_message')"></ActionSheetItem>

				<ActionSheetItem v-on:click="copyContent" iconName="type-01" v-bind:textLabel="$t('dd.copy_text')"></ActionSheetItem>
			</ActionSheetGroup>
		</div>
	</ActionSheet>

	<ReactionsPicker v-if="state.reactionMenu.status" v-on:add="addReaction" v-on:close="state.reactionMenu.close"></ReactionsPicker>
</template>

<script>
	import { defineComponent, ref, computed, reactive, defineAsyncComponent } from 'vue';
	import { useChatStore } from '@M/store/chats/chat.store';
	import { useAuthStore } from '@M/store/auth/auth.store.js';
	import { useMenu } from '@/kernel/vue/composables/menu/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';

	import Avatar2ExtraSmall from '@M/components/general/avatars/Avatar2ExtraSmall.vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import DropdownButton from '@M/components/general/dropdowns/DropdownButton.vue';
	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import ActionSheetItem from '@M/components/general/sheets/ActionSheetItem.vue';
	import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';
	import ActionSheetReactions from '@M/components/general/sheets/ActionSheetReactions.vue';
	import ReactionsViewer from '@/kernel/vue/components/reactions/ReactionsViewer.vue';
	import ReactionsPicker from '@M/views/messenger/children/chat/parts/ReactionsPicker.vue';
	import ChatMessageReply from '@M/views/messenger/children/chat/parts/ChatMessageReply.vue';

	export default defineComponent({
		props: {
			messageData: {
				type: Object,
				required: true
			}
		},
		emits: ['delete', 'reply', 'copy'],
		setup: function (props, context) {
			const chatStore = useChatStore();
			const authStore = useAuthStore();

			const userData = computed(() => {
				return authStore.userData;
			});

			const messageData = ref(props.messageData);
			const state = reactive({
				menuButton: false,
				mainMenu: useMenu(),
				reactionMenu: useMenu()
			});

			const isSender = computed(() => {
				return userData.value.id == messageData.value.user_id;
			});

			const isNotDeleted = computed(() => {
				return ! messageData.value.meta.is_deleted;
			});

			return {
				isSender: isSender,
				state: state,
				isNotDeleted: isNotDeleted,
				messageUser: computed(() => {
					return messageData.value.relations.user;
				}),
				replyData: computed(() => {
					return messageData.value.relations.parent;
				}),
				messageColor: computed(() => {
					return messageData.value.relations.participant.color;
				}),
				reply: function() {
					colibriEventBus.emit('messenger-message:reply', {
						messageData: messageData.value
					});
				},
				copyContent: function() {
					context.emit('copy', messageData.value);
				},
				addReaction: (reactionId) => {
					if (isNotDeleted.value) {
						state.reactionMenu.close();
						chatStore.addReaction(reactionId, messageData.value.id);
					}
                },
				deleteMessage: function() {
					context.emit('delete', {
						messageId: messageData.value.id,
						isSender: isSender.value
					});
				},
				isMessageSeen: computed(() => {
					if(chatStore.otherParticipants) {
						return chatStore.otherParticipants.some(function(p) {
							return p.last_read_message_id >= messageData.value.id;
						});
					}

					return false;
				}),
				hasReactions: computed(() => {
                    return messageData.value.relations.reactions.length;
                }),
				hasLinkSnapshot: computed(() => {
					return messageData.value.relations?.link_snapshot;
				}),
				showMenuButton: () => {
					if(! isNotDeleted.value) {
						return false;
					}

					// TODO: Improve UX in future.
					state.menuButton = true;

					debounce(() => {
						state.menuButton = false;
					}, 1000);
				}
			};
		},
		components: {
			Avatar2ExtraSmall: Avatar2ExtraSmall,
			PrimaryIconButton: PrimaryIconButton,
			DropdownButton: DropdownButton,
			ActionSheet: ActionSheet,
			ActionSheetItem: ActionSheetItem,
			ActionSheetGroup: ActionSheetGroup,
			ActionSheetReactions: ActionSheetReactions,
			ReactionsViewer: ReactionsViewer,
			ReactionsPicker: ReactionsPicker,
			ChatMessageReply: ChatMessageReply,
			LinkSnapshot: defineAsyncComponent(() => {
                return import('@M/components/media/links/LinkSnapshot.vue');
            })
		}
	});
</script>