<template>
	<div class="h-16 flex justify-between items-center px-6">
		<div v-on:click="openInfo" class="inline-flex items-center cursor-pointer leading-none">
			<AvatarSmall v-bind:avatarSrc="chatData.chat_info.avatar_url"></AvatarSmall>
			<div class="ml-3">
				<h4 class="text-par-l font-bold mb-1 text-lab-pr">
					<span v-html="chatData.chat_info.name"></span> <VerificationBadge v-if="chatData.chat_info.verified" size="sm"></VerificationBadge>
				</h4>
				<span class="block text-par-s text-lab-sc">
					<template v-if="chatData.is_group">
						{{ $t('chat.participants_count', { count: chatData.chat_info.participants_count.formatted }) }}
					</template>
					<template v-else>
						<template v-if="isTyping">
							{{ $t('chat.typing') }}
						</template>
						<template v-else>
							{{ $t('labels.was_online_at', { time: chatData.chat_info.last_active.formatted }) }}
						</template>
					</template>
				</span>
			</div> 
		</div>
		<div class="inline-flex gap-2.5">
			<PrimaryIconButton v-on:click="$comingSoon" iconName="search-lg"></PrimaryIconButton>
			<template v-if="chatData.is_group">
				<RouterLink v-bind:to="{ name: 'messenger_group_show', params: { chat_id: chatData.id } }">
					<PrimaryIconButton iconName="info-circle" iconType="line"></PrimaryIconButton>
				</RouterLink>
			</template>
			<template v-else>
				<PrimaryIconButton v-on:click="$comingSoon" v-bind:disabled="true" iconName="phone" iconType="line"></PrimaryIconButton>
				<PrimaryIconButton v-on:click="openInfo" iconName="layout-right" iconType="line"></PrimaryIconButton>
			</template>
		</div>
	</div>
</template>

<script>
	import { defineComponent, ref, computed } from 'vue';
	import { useChatStore } from '@D/store/chats/chat.store.js';
	import { useRouter } from 'vue-router';
	
	import AvatarSmall from '@D/components/general/avatars/AvatarSmall.vue';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
	
	export default defineComponent({
		emits: ['info'],
		props: {
			typingUser: {
				type: Object,
				default: {
					is_typing: false,
					user: null
				}
			}
		},
		setup: function (props, context) {
			const chatStore = useChatStore();
			const chatData = ref(chatStore.chatData);
			const router = useRouter();

			return {
				chatData: chatData,
				isTyping: computed(() => {
					return props.typingUser.is_typing === true;
				}),
				openInfo: () => {
					if(chatData.value.type == 'group') {
						router.push({ name: 'messenger_group_show', params: { 
								chat_id: chatData.value.chat_id,
								group_id: chatData.value.chat_info.id
							}
						});
					} else {
						context.emit('info');
					}
				}
			};
		},
		components: {
			AvatarSmall: AvatarSmall,
			PrimaryIconButton: PrimaryIconButton
		}
	});
</script>