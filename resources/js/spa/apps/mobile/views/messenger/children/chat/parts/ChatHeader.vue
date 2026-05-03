<template>
	<div class="px-4 py-3 flex items-center leading-none gap-2.5 border-b border-bord-tr">
		<div class="shrink-0">
			<PrimaryIconButton 
				v-on:click="$emit('close')"
				buttonColor="text-lab-pr"
				iconSize="icon-medium"
			iconName="chevron-left"></PrimaryIconButton>
		</div>
		<div class="shrink-0 cursor-pointer" v-on:click="openInfo">
			<AvatarExtraSmall v-bind:avatarSrc="chatData.chat_info.avatar_url"></AvatarExtraSmall>
		</div>
		<div class="flex-1 overflow-hidden cursor-pointer" v-on:click="openInfo">
			<Name v-bind:name="chatData.chat_info.name"></Name>
			<p class="text-par-s truncate mt-1 text-lab-sc">
				<template v-if="isTyping">
					<span class="text-green-900 font-medium">
						<template v-if="chatData.is_group">
							{{ $t('chat.user_is_typing', { name: typingUser.user.name }) }}
						</template>
						<template v-else>
							{{ $t('chat.typing') }}
						</template>
					</span>
				</template>
				<template v-else>
					<template v-if="chatData.is_group">
						{{ $t('chat.participants_count', { count: chatData.chat_info.participants_count.formatted }) }}
					</template>
					<template v-else>
						{{ $t('labels.was_online_at', { time: chatData.chat_info.last_active.formatted }) }}
					</template>
				</template>
			</p>
		</div>
		<div class="shrink-0">
			<PrimaryIconButton v-on:click="openInfo" iconType="line" v-bind:iconName="chatData.is_group ? 'info-circle' : 'info-circle'"></PrimaryIconButton>
		</div>
	</div>
	<ChatInfoSheet
		v-if="state.mainMenu.status"
		v-bind:chatData="chatData"
		v-on:open-participants="state.participantsMenu.open"
	v-on:close="state.mainMenu.close"></ChatInfoSheet>

	<ChatParticipants
		v-if="state.participantsMenu.status"
		v-bind:chatData="chatData"
	v-on:close="state.participantsMenu.close"></ChatParticipants>
</template>

<script>	
	import { defineComponent, computed, reactive } from 'vue';
	import { useMenu } from '@/kernel/vue/composables/menu/index.js';
	import { useRouter } from 'vue-router';

	import AvatarExtraSmall from '@M/components/general/avatars/AvatarExtraSmall.vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import ChatInfoSheet from '@M/views/messenger/children/chat/parts/ChatInfoSheet.vue';
	import ChatParticipants from '@M/views/messenger/children/chat/parts/participants/ChatParticipants.vue';

	export default defineComponent({
		emits: ['close'],
		props: {
			typingUser: {
				type: Object,
				default: {
					is_typing: false,
					user: null
				}
			},
			chatData: {
				type: Object,
				required: true
			}
		},
		setup: function(props, context) {
			const router = useRouter();
			const state = reactive({
				mainMenu: useMenu(),
				participantsMenu: useMenu()
			});

			return {
				state: state,
				isTyping: computed(() => {
					return props.typingUser.is_typing === true;
				}),
				openInfo: () => {
					if(props.chatData.is_group) {
						router.push({ name: 'messenger_group', params: { chat_id: props.chatData.id } });
					} else {
						state.mainMenu.open();
					}
				},
			}
		},
		components: {
			AvatarExtraSmall: AvatarExtraSmall,
			PrimaryIconButton: PrimaryIconButton,
			ChatInfoSheet: ChatInfoSheet,
			ChatParticipants: ChatParticipants
		}
	});
</script>