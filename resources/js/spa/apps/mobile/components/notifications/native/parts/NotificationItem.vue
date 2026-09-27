<template>
    <div class="hover:bg-fill-fv smoothing px-4 py-4 overflow-hidden">
        <div class="flex">
            <div v-if="isMarketing" class="shrink-0">
                <div class="size-small-avatar rounded-xl bg-filled overflow-hidden flex-center">
                    <img class="size-full object-cover" v-bind:src="notificationData.actor.avatar_url" alt="Platform">
                </div>
            </div>
            <div v-else class="shrink-0 size-small-avatar relative">
                <AvatarSmall v-bind:avatarSrc="notificationData.actor.avatar_url"></AvatarSmall>
                <span v-if="! notificationData.is_read" class="absolute border-2 border-bg-pr bottom-0 right-0 size-3 rounded-full bg-red-900 inline-block"></span>
            </div>
            <div class="flex-1 ml-2 leading-none">
                <template v-if="isMarketing">
                    <div v-if="marketingBannerUrl" class="block mb-2 overflow-hidden rounded-xl">
                        <img v-bind:src="marketingBannerUrl" alt="Banner" class="w-full max-h-48 object-cover smoothing">
                    </div>
                    <div class="block">
                        <span v-bind:class="[marketingTitleSize, marketingTitleWeight]" class="text-lab-pr mr-1 leading-tight inline-block">
                            {{ notificationData.entity.title }}
                        </span>
                    </div>
                    <div class="block mt-1">
                        <p class="text-par-m text-lab-sc leading-5 whitespace-pre-line">{{ notificationData.message }}</p>
                    </div>
                    <p v-if="notificationData.entity.content" class="text-par-s text-lab-sc whitespace-pre-line leading-5 mt-1">
                        {{ notificationData.entity.content }}
                    </p>
                    <div v-if="marketingPosts.length" class="mt-2 space-y-1.5">
                        <div v-for="post in marketingPosts" v-bind:key="post.hash_id" v-on:click="handlePostRouting(post)"
                             class="flex items-center gap-2 bg-filled rounded-xl p-2 cursor-pointer active:bg-fill-fv smoothing">
                            <div v-if="post.cover_url" class="shrink-0 size-11 rounded-lg overflow-hidden">
                                <img v-bind:src="post.cover_url" alt="" class="size-full object-cover">
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-par-s font-medium text-lab-pr leading-4 truncate">{{ post.excerpt }}</p>
                                <p class="text-par-s text-lab-sc mt-0.5 truncate">
                                    {{ post.author_name }} · {{ post.reactions_count }} {{ $t('notifs.marketing_reactions') }} · {{ post.comments_count }} {{ $t('notifs.marketing_comments') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <a v-if="notificationData.metadata.destination_url" v-bind:href="notificationData.metadata.destination_url"
                       target="_blank" rel="noopener"
                       class="text-par-s font-medium text-brand-900 block mt-2">
                        {{ $t('notifs.view_details') }}
                    </a>
                </template>
                <template v-else>
                    <div class="block">
                        <span class="font-semibold text-par-m text-lab-pr mr-1">
                            {{ notificationData.actor.name }}<template v-if="notificationData.actor.verified">&nbsp;<VerificationBadge size="xs"></VerificationBadge></template>
                        </span>
                        <span v-on:click="handleRouting" class="text-par-m text-lab-sc leading-5 cursor-pointer hover:text-lab-pr">
                            {{ notificationData.message }}<template v-if="notificationData.entity.content">
                                - <span class="font-normal">&quot;{{ notificationData.entity.content }}&quot;</span>

                                <span v-if="isReaction" v-on:click="handleRouting" class="ml-2 align-middle cursor-pointer overflow-hidden inline-block">
                                    <img class="size-4" v-bind:src="notificationData.metadata.reaction_image_url" alt="Emoji">
                                </span>
                            </template>
                        </span>
                    </div>
                </template>
                <div class="block">
                    <time class="text-par-s text-lab-sc">{{ notificationData.date.time_ago }}</time>
                </div>
            </div>
            <div v-if="! isMarketing && hasPreviewImage" class="shrink-0 ml-4">
                <div v-on:click="handleRouting" class="size-11 overflow-hidden rounded-md cursor-pointer">
                    <img class="size-full object-cover smoothing hover:scale-110" v-bind:src="notificationData.entity.preview_lqip_base64" alt="Image">
                </div>
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
    import { useNotificationsStore } from '@M/store/notifications/notifications.store.js';

    import AvatarSmall from '@M/components/general/avatars/AvatarSmall.vue';
    import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';
    import FollowAcceptPillButton from '@M/components/inter-ui/buttons/follows/FollowAcceptPillButton.vue';
    import FollowDeclinePillButton from '@M/components/inter-ui/buttons/follows/FollowDeclinePillButton.vue';

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
                        name: 'stories_index_page',
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
                        name: 'wallet_page'
                    }
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
                marketingBannerUrl: computed(() => {
                    return metadata.value.image_url || '';
                }),
                marketingTitleSize: computed(() => {
                    const sizeMap = { sm: 'text-par-s', md: 'text-par-m', lg: 'text-par-l' };
                    const style = metadata.value.style || {};

                    return sizeMap[style.title_size] || 'text-par-m';
                }),
                marketingTitleWeight: computed(() => {
                    const weightMap = { normal: 'font-normal', medium: 'font-medium', semibold: 'font-semibold', bold: 'font-bold' };
                    const style = metadata.value.style || {};

                    return weightMap[style.title_weight] || 'font-semibold';
                }),
                marketingPosts: computed(() => {
                    const posts = metadata.value.posts;

                    return Array.isArray(posts) ? posts : [];
                }),
                handlePostRouting: (post) => {
                    if(post && post.hash_id) {
                        context.emit('route', {
                            name: 'publication_index',
                            params: {
                                hash_id: post.hash_id
                            }
                        });
                    }
                },
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
            PrimaryPillButton: PrimaryPillButton,
            FollowAcceptPillButton: FollowAcceptPillButton,
            FollowDeclinePillButton: FollowDeclinePillButton
        }
    });
</script>