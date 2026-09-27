<template>
    <div class="hover:bg-fill-fv smoothing px-4 py-2.5">
        <div class="flex relative">
            <div v-if="! notificationData.is_read" class="absolute top-1 -left-3">
                <span class="size-1.5 rounded-full bg-brand-900 inline-block"></span>
            </div>
            <div v-if="isMarketing" class="shrink-0">
                <div class="size-8 rounded-md bg-filled overflow-hidden flex-center">
                    <img class="size-full object-cover" v-bind:src="notificationData.actor.avatar_url" alt="Platform">
                </div>
            </div>
            <div v-else class="shrink-0">
                <AvatarSmall v-bind:avatarSrc="notificationData.actor.avatar_url"></AvatarSmall>
            </div>
            <div class="flex-1 ml-2 leading-none">
                <template v-if="isMarketing">
                    <div class="block">
                        <span class="font-medium text-par-n text-lab-pr mr-1">
                            {{ notificationData.entity.title }}
                        </span>
                    </div>
                    <div class="block mt-1">
                        <p class="text-par-s text-lab-pr2 whitespace-pre-line leading-4">{{ notificationData.message }}</p>
                    </div>
                    <p v-if="notificationData.entity.content" class="text-par-s text-lab-sc whitespace-pre-line block mt-1 leading-4">
                        {{ notificationData.entity.content }}
                    </p>
                    <div v-if="notificationData.metadata.destination_url" class="block mt-2">
                        <a v-bind:href="notificationData.metadata.destination_url" target="_blank" rel="noopener"
                           class="text-par-s font-medium text-brand-900 hover:text-lab-pr">
                            {{ $t('notifs.view_details') }}
                        </a>
                    </div>
                </template>
                <template v-else>
                    <div class="block">
                        <span class="font-medium text-par-n text-lab-pr mr-1">
                            {{ notificationData.actor.name }}<template v-if="notificationData.actor.verified">&nbsp;<VerificationBadge size="xs"></VerificationBadge></template>
                        </span>
                        <span v-on:click="handleRouting" class="text-par-s text-lab-pr2 leading-4 cursor-pointer hover:text-lab-pr">
                            {{ notificationData.message }}<template v-if="notificationData.entity.content">
                                :<span class="font-normal">&quot;{{ notificationData.entity.content }}&quot;</span>
                            </template>
                        </span>
                    </div>
                    <div v-if="hasPreviewImage" class="block my-1">
                        <div v-on:click="handleRouting" class="size-10 overflow-hidden rounded-md cursor-pointer">
                            <img class="size-full object-cover smoothing hover:scale-110" v-bind:src="notificationData.entity.preview_lqip_base64" alt="Image">
                        </div>
                    </div>
                </template>
                <div class="block">
                    <time class="text-par-s text-lab-sc">{{ notificationData.date.time_ago }}</time>
                </div>
            </div>
            <div v-if="! isMarketing && isReaction" v-on:click="handleRouting" class="shrink-0 ml-2 cursor-pointer overflow-hidden">
                <img class="size-6 smoothing hover:scale-110" v-bind:src="notificationData.metadata.reaction_image_url" alt="Emoji">
            </div>
            <div v-else-if="! isMarketing && isViewable" class="shrink-0 ml-4">
                <PrimaryPillButton v-on:click="handleRouting" v-bind:buttonText="$t('labels.view')" buttonSize="md"></PrimaryPillButton>
            </div>
            <div v-else-if="! isMarketing && isFollowRequest" class="shrink-0 ml-4 flex items-center gap-2">
                <FollowDeclinePillButton
                    v-if="! metadata.is_approved"
                    v-bind:followableId="notificationData.entity.id"
                    v-on:click="handleFollowDecline"
                buttonSize="md"></FollowDeclinePillButton>
                <FollowAcceptPillButton
                    v-bind:followableId="notificationData.entity.id"
                    v-bind:isApproved="metadata.is_approved"
                    v-on:click="handleFollowAccept"
                buttonSize="md"></FollowAcceptPillButton>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, computed } from 'vue';
    import { useNotificationsStore } from '@D/store/notifications/notifications.store.js';

    import AvatarSmall from '@D/components/general/avatars/AvatarSmall.vue';
    import DropdownButton from '@D/components/general/dropdowns/parts/DropdownButton.vue';
    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
    import FollowAcceptPillButton from '@D/components/inter-ui/buttons/follows/FollowAcceptPillButton.vue';
    import FollowDeclinePillButton from '@D/components/inter-ui/buttons/follows/FollowDeclinePillButton.vue';

    export default defineComponent({
        props: {
            notificationData: {
                type: Object,
                default: {}
            }
        },
        setup: function(props, context) {
            const notificationsStore = useNotificationsStore();
            const notificationRoute = computed(() => {
                if(['post.reacted', 'post.commented', 'post.mentioned'].includes(props.notificationData.type)) {
                    return {
                        name: 'publication_index',
                        params: {
                            hash_id: props.notificationData.entity.hash_id
                        }
                    }
                }
                else if(['comment.mentioned', 'comment.reacted'].includes(props.notificationData.type)) {
                    return {
                        name: 'publication_index',
                        params: {
                            hash_id: props.notificationData.entity.post_hash_id
                        }
                    }
                }
                else if(['story.mentioned'].includes(props.notificationData.type)) {
                    return {
                        name: 'stories_index',
                        params: {
                            story_uuid: props.notificationData.entity.story_uuid
                        }
                    }
                }
                else if(['user.followed-requested', 'account-linked', 'user.followed', 'user.follow-accepted'].includes(props.notificationData.type)) {
                    return {
                        name: 'profile_index',
                        params: {
                            id: props.notificationData.entity.username
                        }
                    }
                }
                else if(['important.wallet-deposit'].includes(props.notificationData.type)) {
                    return {
                        name: 'wallet_index'
                    };
                }

                return '#';
            });

            const metadata = computed(() => {
                if(props.notificationData.metadata) {
                    return props.notificationData.metadata;
                }

                return {};
            });
            
            return {
                handleRouting: () => {
                    context.emit('route', notificationRoute.value);
                },
                metadata: metadata,
                isMarketing: computed(() => {
                    if(props.notificationData.type === 'marketing.platform') {
                        return true;
                    }

                    return false;
                }),
                isReaction: computed(() => {
                    if(['post.reacted', 'comment.reacted'].includes(props.notificationData.type)) {
                        return true;
                    }

                    return false;
                }),
                isViewable: computed(() => {
                    if(metadata.value.is_viewable) {
                        return true;
                    }

                    return false;
                }),
                isFollowRequest: computed(() => {
                    if(props.notificationData.type === 'user.followed-requested') {
                        return true;
                    }

                    return false;
                }),
                hasPreviewImage: computed(() => {
                    if(props.notificationData.entity) {
                        if(props.notificationData.entity.preview_lqip_base64) {
                            return true;
                        }
                    }

                    return false;
                }),
                handleFollowAccept: function() {
                    debounce(() => {
                        notificationsStore.deleteNotification(props.notificationData.id);
                    }, 2500);
                },
                handleFollowDecline: function() {
                    debounce(() => {
                        notificationsStore.deleteNotification(props.notificationData.id);
                    }, 2500);
                }
            }
        },
        components: {
            AvatarSmall: AvatarSmall,
            DropdownButton: DropdownButton,
            PrimaryPillButton: PrimaryPillButton,
            FollowAcceptPillButton: FollowAcceptPillButton,
            FollowDeclinePillButton: FollowDeclinePillButton
        }
    });
</script>