import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

/**
 * 访客精选公开帖子流 store（desktop）。
 * 数据形状与登录态 timeline store 对齐；数据源 /guest/v1/feed。
 */
const useGuestFeedStore = defineStore('desktop_guest_feed_store', {
    state: function() {
        return {
            posts: [],
            filter: {
                page: 1,
            },
        };
    },
    actions: {
        initialLoad: async function() {
            if (this.posts.length) return;

            try {
                const response = await this.fetch();

                this.posts = response.data.data;
            } catch (error) {
                this.posts = [];
            }
        },
        loadNextPage: async function() {
            this.filter.page += 1;

            try {
                const response = await this.fetch();

                const content = response.data.data;

                if (content.length) {
                    this.posts = this.posts.concat(content);
                }

                return content.length > 0;
            } catch (error) {
                this.filter.page -= 1;

                return false;
            }
        },
        fetch: function() {
            return colibriAPI().guest().params({
                filter: this.filter,
            }).getFrom('feed');
        },
        reset: function() {
            this.posts = [];
            this.filter.page = 1;
        },
    },
});

export { useGuestFeedStore };
