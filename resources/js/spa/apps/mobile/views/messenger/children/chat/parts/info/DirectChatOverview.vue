<template>
	<div class="block">
		<div class="flex justify-center mb-2">
			<AvatarMedium v-bind:avatarSrc="chatData.chat_info.avatar_url"></AvatarMedium>
		</div>
		<div class="text-center leading-snug">
			<h2 class="text-title-3 font-bold text-lab-pr">
				{{ chatData.chat_info.name }} <VerificationBadge v-if="chatData.chat_info.verified" size="sm"></VerificationBadge>
			</h2>
			<p class="text-par-s text-lab-sc">
				{{ $t('labels.was_online_at', { time: chatData.chat_info.last_active.formatted }) }}
			</p>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	import AvatarMedium from '@M/components/general/avatars/AvatarMedium.vue';

	export default defineComponent({
		props: {
			chatData: {
				type: Object,
				required: true
			}
		},
		components: {
			AvatarMedium: AvatarMedium,
		}
	});
</script>