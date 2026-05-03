import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const useTimelineStore = defineStore('mobile_timeline_store', {
    // This is used to tell the postDeleteListener to listen to this store
    // This is used only for timeline stores, on desktop and mobile with the same logic.
    
    deleteAware: true,
    state: function() {
		return {
			posts: [],
            update: [],
			filter: {
				page: 1
			}
		}
	},
    actions: {
        updateFeed: async function() {
            await colibriAPI().userTimeline().params({
                filter: {
                    onset: this.posts.at(0).id
                }
            }).getFrom('feed').then((response) => {
                this.update = response.data.data;
            }).catch((error) => {
                if(error.response) {
                    this.update = [];
                }
            });
        },
        applyUpdate: function() {
            // Check if post already exists before adding
            // Otherwise, add the post to the beginning of the posts array.

            this.update.forEach((postItem) => {
                const exists = this.posts.slice(0, this.update.length).some((post) => {
                    return post.id === postItem.id;
                });
                
                if (! exists) {
                    this.posts.unshift(postItem);
                }
            });

            this.update = [];
        },
        initialLoad: async function() {
            let state = this;

            if (! state.posts.length) {
                this.load().then(function(response) {
                    state.posts = response.data.data;
                }).catch(function(error) {
                    state.posts = [];
                });
            }
        },
        loadNextPage: async function() {
            this.filter.page += 1;

            return await this.load();
        },
        load: async function() {
            return await colibriAPI().userTimeline().params({
                filter: this.filter
            }).getFrom('feed');
        },
        appendPosts: function(posts) {
            this.posts = this.posts.concat(posts);
        },
        prependPost: function(postData) {
            this.posts.unshift(postData);

            return this.posts;
        },
        removePost: function(postId) {
            let postIndex = this.posts.findIndex((item) => {
                return item.id == postId;
            });

            if(postIndex !== -1) {
                this.posts.splice(postIndex, 1);
            }
        },
        updatePoll: function(pollData) {
            const postItem = this.posts.find((item) => {
                return item.id == pollData.post_id;
            });

            if(postItem) {
                postItem.relations.poll = pollData;
            }
        },
        updateCommentCount: function(postId, count) {
            const postItem = this.posts.find((item) => {
                return item.id == postId;
            });

            if(postItem) {
                postItem.comments_count = count;
            }
        }
    }
});

export { useTimelineStore };