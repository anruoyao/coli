<template>
	<div class="block py-3">
		<div class="flex items-center justify-between mb-1 cursor-pointer">
			<AvatarRightSided 
				v-bind:avatarSrc="userData.avatar_url"
				v-bind:verified="userData.verified"
				v-bind:name="userData.name"
				v-bind:linkRoute="{ name: 'profile_index', params: { id: userData.username } }"
			v-bind:caption="userData.caption"></AvatarRightSided>
			<div class="shrink-0">
				<FollowPillButton v-bind:followableId="userData.id" v-bind:relationship="userData.meta.relationship.follow"></FollowPillButton>
			</div>
		</div>
		<div class="mx-small-avatar">
			<div class="pl-2 pr-4">
				<p class="text-par-n text-lab-pr2 line-clamp-2" v-html="userData.bio"></p>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';
	
	import AvatarRightSided from '@D/components/general/avatars/sided/small/AvatarRightSided.vue';
	import FollowPillButton from '@D/components/inter-ui/buttons/follows/FollowPillButton.vue';

	export default defineComponent({
		props: {
			userData: {
				type: Object,
				required: true
			}
		},
		components: {
			FollowPillButton: FollowPillButton,
			AvatarRightSided: AvatarRightSided
		}
	});
</script>