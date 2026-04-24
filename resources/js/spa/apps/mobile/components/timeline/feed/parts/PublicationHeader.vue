<template>
	<AvatarRightSided v-bind:linkRoute="{ name: 'profile_index', params: { id: postData.relations.user.username } }"
		v-bind:avatarSrc="postData.relations.user.avatar_url"
		v-bind:name="postData.relations.user.name"
		v-bind:caption="postData.relations.user.caption"
	v-bind:verified="postData.relations.user.verified"></AvatarRightSided>
</template>

<script>
	import { defineComponent, ref, computed } from 'vue';

	import AvatarRightSided from '@M/components/general/avatars/sided/small/AvatarRightSided.vue';

	export default defineComponent({
		props: {
			postData: {
				type: Object,
				required: true
			}
		},
		setup: (props) => {
			const postData = ref(props.postData);

			return {
				postUserCaption: computed(() => {
                    return `${postData.value.relations.user.caption} · ${postData.value.date.time_ago}`;
                }),
			}
		},
		components: {
			AvatarRightSided: AvatarRightSided
		}
	})
</script>