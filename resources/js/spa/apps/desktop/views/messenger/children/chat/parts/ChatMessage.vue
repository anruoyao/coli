<template>
	<div v-on:dblclick.stop="replyToMessage" v-bind:title="$t('chat.double_click_to_reply')" class="flex group py-2 px-6 items-start" v-bind:class="[displayMessageControls ? 'bg-brand-900/5' : 'hover:bg-fill-fv']" v-on:contextmenu.prevent="toggleMainDropdown">
		<div class="flex-1 group">
			<div class="flex overflow-hidden">
				<div class="shrink-0">
					<AvatarSmall v-bind:avatarSrc="messageUser.avatar_url"></AvatarSmall>
				</div>
				<div class="flex-1 ml-3">
					<div class="w-96">
						<div class="leading-none">
							<strong class="text-par-n font-semibold" v-bind:style="{ color: messageColor }">
								{{ messageUser.name }} <VerificationBadge v-if="messageUser.verified" size="xs"></VerificationBadge>
							</strong>
							<time class="text-cap-l text-lab-sc ml-1">{{ messageData.date.time_ago }}</time>
						</div>
						<div class="block">
							<template v-if="messageData.has_parent">
								<div class="mt-1.5">
									<ChatMessageReply v-bind:replyData="replyData"></ChatMessageReply>
								</div>
							</template>
							<div class="block">
								<div v-if="isTranslatable && state.isTranslated" class="leading-none my-1">
									<TextTranslateButton v-on:click="cancelTranslation"
									v-bind:buttonText="$t('labels.show_untranslated')"></TextTranslateButton>
								</div>
								<div v-if="isNotDeleted" class="text-par-l markdown-text leading-snug font-medium text-lab-pr2" v-html="$mdInline(messageContent)"></div>
								<div v-else class="flex mt-1">
									<p class="text-lab-sc text-par-m leading-snug">
										{{ $t('chat.message_is_deleted') }}
									</p>
								</div>
								<div v-if="isTranslatable && isNotDeleted && state.isTranslated" class="mt-2">
									<TranslationService></TranslationService>
								</div>
							</div>
							<template v-if="hasLinkSnapshot">
								<div class="w-96 mt-3">
									<LinkSnapshot v-bind:linkSnapshot="messageData.relations.link_snapshot"></LinkSnapshot>
								</div>
							</template>
						</div>
						<div class="block mt-1" v-if="isNotDeleted && hasReactions">
							<ReactionsViewer v-on:add="addReaction" v-bind:reactions="messageData.relations.reactions"></ReactionsViewer>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div v-if="isNotDeleted" class="shrink-0 inline-flex items-center leading-none" v-bind:class="[displayMessageControls ? 'visible' : 'invisible group-hover:visible']">
			<div class="shrink-0 transform -scale-x-100">
				<PrimaryIconButton v-on:click.stop="replyToMessage" iconName="share-06" iconSize="icon-small" iconType="line"></PrimaryIconButton>
			</div>
			<div class="shrink-0 relative">
				<PrimaryIconButton v-on:click.stop="openReactionsPicker" iconName="heart-rounded" iconSize="icon-small" iconType="line"></PrimaryIconButton>
				<PrimaryTransition>
					<div class="absolute right-0 top-8 origin-top-left z-20">
						<ReactionsPicker 
							v-if="state.isReactionPickerOpen" 
							v-on:add="addReaction"
						v-outside-click="closeReactionsPicker"></ReactionsPicker>
					</div>
				</PrimaryTransition>
			</div>
			<div class="shrink-0 relative">
				<div class="opacity-80 hover:opacity-100">
					<DropdownButton v-on:click.stop="toggleMainDropdown"></DropdownButton>
				</div>
				<div class="absolute top-10 right-0 z-50" v-if="state.isDropdownOpen">
					<DropdownMenu v-outside-click="toggleMainDropdown" v-on:click="toggleMainDropdown">
						<DropdownReactions v-on:add="addReaction"></DropdownReactions>
						<DropdownMenuItem v-on:click="openReactionsPicker" iconName="heart-rounded" v-bind:textLabel="$t('dd.add_reaction')"></DropdownMenuItem>
						
						<template v-if="isTranslatable">
							<DropdownMenuItem v-if="state.isTranslated" v-on:click="cancelTranslation" iconName="translate-01" v-bind:textLabel="$t('dd.show_untranslated')"></DropdownMenuItem>
							<DropdownMenuItem v-else v-on:click="translate" iconName="translate-01" v-bind:textLabel="$t('dd.translate')"></DropdownMenuItem>
						</template>
						
						<DropdownMenuItem v-on:click="replyToMessage" iconName="pencil-line" v-bind:textLabel="$t('dd.message.reply', { name: messageUser.name })"></DropdownMenuItem>
						<DropdownMenuItem v-on:click="copyMessageText" iconName="type-01" v-bind:textLabel="$t('dd.copy_text')"></DropdownMenuItem>
						<Border/>
						<DropdownMenuItem v-on:click="deleteMessage" itemColor="text-red-900" iconName="trash-04" v-bind:textLabel="$t('dd.message.delete_message')"></DropdownMenuItem>
					</DropdownMenu>
				</div>
			</div>
		</div>
		<div v-if="isSender" class="shrink-0 size-8 inline-flex-center ml-2">
			<span v-if="isMessageSeen" class="size-icon-small" v-bind:style="{ color: messageColor }">
				<SvgIcon type="line" name="message-double-check"></SvgIcon>
			</span>	
			<span v-else class="size-icon-small text-lab-sc">
				<SvgIcon type="line" name="message-check"></SvgIcon>
			</span>	
		</div>
	</div>
</template>

<script>
	import { defineComponent, ref, computed, reactive, defineAsyncComponent } from 'vue';
	import { useChatStore } from '@D/store/chats/chat.store.js';
	import { useAuthStore } from '@D/store/auth/auth.store.js';
	import { colibriTranslator } from '@/kernel/services/translator/index.js';

	import AvatarSmall from '@D/components/general/avatars/AvatarSmall.vue';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
	import DropdownButton from '@D/components/general/dropdowns/parts/DropdownButton.vue';
    import DropdownMenu from '@D/components/general/dropdowns/parts/DropdownMenu.vue';
    import DropdownMenuItem from '@D/components/general/dropdowns/parts/DropdownMenuItem.vue';
	import DropdownReactions from '@D/components/general/dropdowns/parts/DropdownReactions.vue';
	import ChatMessageReply from '@D/views/messenger/children/chat/parts/ChatMessageReply.vue';
	import TranslationService from '@D/components/general/TranslationService.vue';
	import TextTranslateButton from '@D/components/inter-ui/buttons/TextTranslateButton.vue';

	export default defineComponent({
		props: {
			messageData: {
				type: Object,
				required: true
			}
		},
		emits: ['delete', 'reply', 'copy'],
		setup: function (props, context) {
			const state = reactive({
				isDropdownOpen: false,
				isReactionPickerOpen: false,
				isTranslating: false,
				isTranslated: false
			});

			const messageTranslatedContent = ref('');
			const authStore = useAuthStore();
			const chatStore = useChatStore();
			const userData = ref(authStore.userData);
			const messageData = ref(props.messageData);

			const openReactionsPicker = function() {
				state.isDropdownOpen = false;
				
				if (isNotDeleted.value) {
					debounce(() => {
						state.isReactionPickerOpen = true;
					}, 50);
				}
            }

            const closeReactionsPicker = function() {
                state.isReactionPickerOpen = false;
            }

			const isNotDeleted = computed(() => {
				return !messageData.value.meta.is_deleted;
			});

			const toggleMainDropdown = () => {
				closeReactionsPicker();
				
				if (isNotDeleted.value) {
					state.isDropdownOpen = !state.isDropdownOpen;
				}
			}

			const isSender = computed(() => {
				return userData.value.id == messageData.value.user_id;
			});

			return {
				state: state,
				isSender: isSender,
				closeReactionsPicker: closeReactionsPicker,
				openReactionsPicker: openReactionsPicker,
				messageData: messageData,
				messageUser: computed(() => {
					return messageData.value.relations.user;
				}),
				messageColor: computed(() => {
					return messageData.value.relations.participant.color;
				}),
				replyData: computed(() => {
					return messageData.value.relations.parent;
				}),
				messageContent: computed(() => {
					return state.isTranslated ? messageTranslatedContent.value : messageData.value.content;
				}),
				toggleMainDropdown: toggleMainDropdown,
				addReaction: (reactionId) => {
                    closeReactionsPicker();

					if (isNotDeleted.value) {
						chatStore.addReaction(reactionId, messageData.value.id);
					}
                },
				displayMessageControls: computed(() => {
					return state.isDropdownOpen || state.isReactionPickerOpen;
				}),
				hasReactions: computed(() => {
                    return messageData.value.relations.reactions.length;
                }),
				hasLinkSnapshot: computed(() => {
					return messageData.value.relations?.link_snapshot;
				}),
				isNotDeleted: isNotDeleted,
				isTranslatable: computed(() => {
					return messageData.value.meta.is_translatable;
				}),
				deleteMessage: () => {
					context.emit('delete', {
						messageId: messageData.value.id,
						isSender: isSender.value
					});
                },
				replyToMessage: () => {
					context.emit('reply', messageData.value);
				},
				copyMessageText: () => {
					context.emit('copy', messageData.value);
				},
				isMessageSeen: computed(() => {
					if(chatStore.otherParticipants) {
						return chatStore.otherParticipants.some(function(p) {
							return p.last_read_message_id >= messageData.value.id;
						});
					}

					return false;
				}),
				translate: async () => {
                    if (state.isTranslating || state.isTranslated) {
                        return false;
                    }

                    state.isTranslating = true;
                    const translatedText = await colibriTranslator().translate(messageData.value.content);

                    if (translatedText) {
                        messageTranslatedContent.value = translatedText;
                        state.isTranslated = true;
                    }

                    state.isTranslating = false;
                },
				cancelTranslation: () => {
                    state.isTranslated = false;
                    messageTranslatedContent.value = '';
                },
			};
		},
		components: {
			AvatarSmall: AvatarSmall,
			DropdownButton: DropdownButton,
			DropdownMenu: DropdownMenu,
			DropdownMenuItem: DropdownMenuItem,
			PrimaryIconButton: PrimaryIconButton,
			ChatMessageReply: ChatMessageReply,
			TranslationService: TranslationService,
			TextTranslateButton: TextTranslateButton,
			DropdownReactions: DropdownReactions,
			ReactionsPicker: defineAsyncComponent(() => {
                return import('@D/components/reactions/ReactionsPicker.vue');
            }),
			ReactionsViewer: defineAsyncComponent(() => {
                return import('@/kernel/vue/components/reactions/ReactionsViewer.vue');
            }),
			LinkSnapshot: defineAsyncComponent(() => {
                return import('@D/components/media/links/LinkSnapshot.vue');
            })
		}
	});
</script>