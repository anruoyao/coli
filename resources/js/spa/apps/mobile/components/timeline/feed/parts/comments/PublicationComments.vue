<template>
	<ActionSheet v-on:close="$emit('close')">
		<div class="h-full flex flex-col">
			<div class="px-4 pb-4 border-b border-b-bord-pr text-center">
				<SheetTitle v-bind:title="$t('labels.comments')"></SheetTitle>
			</div>
			<template v-if="state.isLoading">
				<div class="flex justify-center py-24">
					<div class="colibri-primary-animation"></div>
				</div>
			</template>
			<template v-else>
				<div class="flex-1 overflow-y-auto">
					<div v-if="threads.length">
						<div v-for="threadItem in threads" v-bind:key="threadItem.root.id">
							<CommentThread
								v-bind:thread="threadItem"
								v-on:toggle-expand="toggleExpand(threadItem)"
								v-on:load-more="loadMore(threadItem)"
								v-on:reply="handleCommentReply"
							v-on:delete="handleCommentDelete"></CommentThread>
						</div>
						<template v-if="state.isLoadingComments">
							<div class="flex justify-center py-4">
								<div class="colibri-primary-animation"></div>
							</div>
						</template>
						<template v-else>
							<LoadmoreButton v-if="! state.noMoreComments" v-on:click="handleLoadMoreComments"></LoadmoreButton>
						</template>
					</div>
					<div v-else class="py-42">
						<TimelineEmptyState v-bind:desc="$t('empty_state.comments.desc')"></TimelineEmptyState>
					</div>
				</div>
	
				<div class="shrink-0 border-t border-t-bord-pr">
					<CommentEditor v-on:created="handleCommentCreate" v-bind:postId="postData.id"></CommentEditor>
				</div>
			</template>
		</div>
	</ActionSheet>
</template>

<script>
	import { defineComponent, reactive, ref, computed, onMounted } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';
	import { useTimelineStore } from '@M/store/timeline/timeline.store.js';
	import { useCommentThreads } from '@/kernel/vue/composables/comment-threads/index.js';

	import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
	import SheetTitle from '@M/components/general/sheets/SheetTitle.vue';
	import TimelineEmptyState from '@M/components/timeline/state/TimelineEmptyState.vue';
	import CommentThread from '@M/components/timeline/feed/parts/comments/parts/CommentThread.vue';
	import CommentEditor from '@M/components/timeline/feed/parts/comments/editor/CommentEditor.vue';
	import LoadmoreButton from '@M/components/inter-ui/buttons/LoadmoreButton.vue';

	export default defineComponent({
		emits: ['close'],
		props: {
			postData: {
				type: Object,
				required: true,
			},
		},
		setup: (props) => {
			const postData = computed(() => {
				return props.postData;
			});

			const timelineStore = useTimelineStore();
			const state = reactive({
				isLoading: true,
                isLoadingComments: false,
                noMoreComments: false
			});

			// 树状评论线程（与 Flutter 端同一套 threaded API）
			const {
				threads,
				setRoots,
				appendRoots,
				toggleExpand,
				loadMore,
				applyCreated,
				applyDeleted,
				findComment
			} = useCommentThreads(computed(() => false));

			const fetchComments = async () => {
                if (! state.noMoreComments && ! state.isLoadingComments) {
                	let cursorId = 0;

					if (threads.value.length) {
						cursorId = Math.min(...threads.value.map((thread) => thread.root.id));
					}

					state.isLoadingComments = true;

					await colibriAPI().userTimeline().params({
						cursor: cursorId,
						threaded: 1
					}).getFrom(`post/${postData.value.hash_id}/comments`).then(function(response) {
						let comments = response.data.data;

						if (comments.length) {
							appendRoots(comments);
						}
						else {
							state.noMoreComments = true;
						}
					}).catch(function(error) {
						state.noMoreComments = true;
					});

					state.isLoadingComments = false;
                }
            }

			onMounted(async () => {
				await fetchComments();

				debounce(() => {
					state.isLoading = false;
				}, 100);
			});
			

			return {
				state: state,
				threads: threads,
				toggleExpand: toggleExpand,
				loadMore: loadMore,
				handleCommentReply: (commentId) => {
					const commentData = findComment(commentId);

					if (commentData) {
						colibriEventBus.emit('publication-comment:reply', {
							commentData: commentData
						});
					}
				},
				handleCommentDelete: (commentId) => {
                    colibriEventBus.emit('confirmation-modal:open', {
                        title: __t('prompt.delete_comment.title'),
                        description: __t('prompt.delete_comment.description'),
                        onConfirm: () => {
                            colibriAPI().userTimeline().with({
                                id: commentId
                            }).delete('post/comment/delete').then((response) => {

								timelineStore.updateCommentCount(postData.value.id, response.data.data.post.comments_count);

								// 主评论整棵线程移除；回复从线程内摘除
                                applyDeleted(commentId);

                                toastSuccess(__t('toast.media.comment_deleted'));
                            }).catch((error) => {
                                if (error.response) {
                                    toastError(error.response.data.message);
                                }
                            });
                        }
                    });
                },
				handleCommentCreate: (commentData) => {
                    const located = applyCreated(commentData.comment);

					// 找不到所属线程（极端情况）：保守重拉保证数据一致
                    if (! located) {
                    	fetchComments();
                    }

                    postData.value.comments_count = commentData.post.comments_count;
                },
				handleLoadMoreComments: async () => {
					await fetchComments();
				}
			}
		},
		components: {
			ActionSheet: ActionSheet,
			CommentThread: CommentThread,
			SheetTitle: SheetTitle,
			TimelineEmptyState: TimelineEmptyState,
			CommentEditor: CommentEditor,
			LoadmoreButton: LoadmoreButton
		},
	});
</script>
