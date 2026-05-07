<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.blocked_accounts')"></PageTitle>
    </div>
    <div>
        <div v-if="state.isLoading" class="space-y-3">
            <PeopleListItemSkeleton v-for="n in 5" v-bind:key="n"></PeopleListItemSkeleton>
        </div>

        <div v-else-if="state.isEmpty" class="flex flex-col items-center justify-center py-16">
            <div class="mb-4">
                <img class="h-36" v-bind:src="$embedder('assets.lottie.empty_timeline')" alt="">
            </div>
            <h3 class="text-par-l font-bold text-lab-pr2 mb-2">
                {{ $t('settings.empty_blocked_list') }}
            </h3>
            <p class="text-par-n text-lab-sc text-center max-w-md">
                {{ $t('settings.empty_blocked_list_desc') }}
            </p>
        </div>

        <div v-else>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-par-l font-bold text-lab-pr2">
                    {{ $t('settings.blocked_people') }}
                    <span class="text-lab-sc text-par-n font-normal ml-1">
                        ({{ state.totalDisplayed }})
                    </span>
                </h3>
            </div>

            <div class="divide-y divide-bord-pr dark:divide-bord-pr">
                <div v-for="userData in state.blockedUsers" v-bind:key="userData.id" class="flex items-center justify-between py-3">
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        <AvatarRightSided
                            v-bind:avatarSrc="userData.avatar_url"
                            v-bind:name="userData.name"
                            v-bind:verified="userData.verified"
                            v-bind:linkRoute="{ name: 'profile_index', params: { id: userData.username } }"
                            v-bind:caption="'@' + userData.username"
                        ></AvatarRightSided>
                    </div>
                    <div class="shrink-0 ml-4">
                        <PrimaryTextButton
                            v-on:click="unblockUser(userData)"
                            v-bind:loading="state.unblockingId === userData.id"
                            v-bind:buttonText="$t('labels.unblock')"
                            buttonColor="text-red-900"
                        ></PrimaryTextButton>
                    </div>
                </div>
            </div>

            <div v-if="state.hasMore" class="flex justify-center py-6">
                <PrimaryTextButton
                    v-on:click="loadMore"
                    v-bind:loading="state.isLoadingMore"
                    v-bind:buttonText="$t('labels.load_more')"
                ></PrimaryTextButton>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, reactive, onMounted } from 'vue';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

    import PageTitle from '@D/components/layout/PageTitle.vue';
    import AvatarRightSided from '@D/components/general/avatars/sided/small/AvatarRightSided.vue';
    import PrimaryTextButton from '@D/components/inter-ui/buttons/PrimaryTextButton.vue';
    import PeopleListItemSkeleton from '@D/components/people/PeopleListItemSkeleton.vue';

    export default defineComponent({
        setup: function() {
            const state = reactive({
                blockedUsers: [],
                cursorId: 0,
                hasMore: false,
                isEmpty: false,
                isLoading: true,
                isLoadingMore: false,
                unblockingId: null,
                totalDisplayed: 0
            });

            function processResponse(response) {
                const data = response.data.data;
                if (Array.isArray(data) && data.length) {
                    const newItems = data.map(function(user) {
                        user.cursor_id = user.cursor_id;
                        return user;
                    });
                    state.blockedUsers = state.blockedUsers.concat(newItems);
                    const lastItem = newItems[newItems.length - 1];
                    state.cursorId = lastItem.cursor_id;
                    state.hasMore = newItems.length >= 30;
                } else {
                    state.hasMore = false;
                    if (!state.blockedUsers.length) {
                        state.isEmpty = true;
                    }
                }
                state.totalDisplayed = state.blockedUsers.length;
            }

            async function fetchInitial() {
                state.isLoading = true;
                state.blockedUsers = [];
                state.cursorId = 0;
                state.isEmpty = false;
                state.hasMore = false;
                try {
                    const response = await colibriAPI().blocks().params({ cursor: 0 }).getFrom('blocked/users');
                    processResponse(response);
                } catch (error) {
                    if (error.response) {
                        alert(error.response.data.message);
                    }
                } finally {
                    state.isLoading = false;
                }
            }

            async function loadMore() {
                state.isLoadingMore = true;
                try {
                    const response = await colibriAPI().blocks().params({ cursor: state.cursorId }).getFrom('blocked/users');
                    const data = response.data.data;
                    if (Array.isArray(data) && data.length) {
                        const newItems = data.map(function(user) {
                            return user;
                        });
                        state.blockedUsers = state.blockedUsers.concat(newItems);
                        const lastItem = newItems[newItems.length - 1];
                        state.cursorId = lastItem.cursor_id;
                        state.hasMore = newItems.length >= 30;
                    } else {
                        state.hasMore = false;
                    }
                    state.totalDisplayed = state.blockedUsers.length;
                } catch (error) {
                    if (error.response) {
                        alert(error.response.data.message);
                    }
                } finally {
                    state.isLoadingMore = false;
                }
            }

            async function unblockUser(userData) {
                state.unblockingId = userData.id;
                try {
                    await colibriAPI().blocks().with({ id: userData.id }).sendTo('unblock/user');
                    state.blockedUsers = state.blockedUsers.filter(function(user) {
                        return user.id !== userData.id;
                    });
                    state.totalDisplayed = state.blockedUsers.length;
                    if (!state.blockedUsers.length) {
                        state.isEmpty = true;
                    }
                } catch (error) {
                    if (error.response) {
                        alert(error.response.data.message);
                    }
                } finally {
                    state.unblockingId = null;
                }
            }

            onMounted(function() {
                fetchInitial();
            });

            return {
                state: state,
                loadMore: loadMore,
                unblockUser: unblockUser
            };
        },
        components: {
            PageTitle: PageTitle,
            AvatarRightSided: AvatarRightSided,
            PrimaryTextButton: PrimaryTextButton,
            PeopleListItemSkeleton: PeopleListItemSkeleton
        }
    });
</script>
