<template>
	<div class="grid grid-cols-2 gap-2">
		<div class="col-span-1">
			<FollowPillButton v-bind:buttonFluid="true" v-bind:relationship="profileData.meta.relationship.follow" v-bind:followableId="profileData.id" buttonSize="md"></FollowPillButton>
		</div>
		<div class="col-span-1">
			<PrimaryPillButton v-on:click="sendMessage" v-bind:loading="state.sendingMessage" v-bind:buttonFluid="true" v-bind:buttonText="$t('dd.user.send_message')" buttonSize="md" buttonRole="stroked"></PrimaryPillButton>
		</div>
	</div>
</template>

<script>
	import { defineComponent, inject, reactive } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { useRouter } from 'vue-router';

	import FollowPillButton from '@M/components/inter-ui/buttons/follows/FollowPillButton.vue';
	import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';
	
	export default defineComponent({
		setup: function() {
			const profileData = inject('profileData');
			const router = useRouter();
			const state = reactive({
				sendingMessage: false
			});

			return {
				profileData: profileData,
				state: state,
				sendMessage: async () => {
					state.sendingMessage = true;

					await colibriAPI().messenger().with({
						user_id: profileData.value.id
					}).sendTo('chats/create').then((response) => {
						let chatData = response.data.data;

						router.push({
							name: 'messenger_chat',
							params: {
								chat_id: chatData.chat_id
							}
						});
					}).catch((error) => {
						if(error.response) {
							toastError(error.response.data.message);
						}
					});
				},
			}
		},
		components: {
			FollowPillButton: FollowPillButton,
			PrimaryPillButton: PrimaryPillButton
		}
	});
</script>