import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

/**
 * 访客精选公开帖子流 store（mobile）。
 *
 * 状态形状与登录态 timeline store 对齐（posts 数组 + filter.page），
 * 但数据源是 /guest/v1/feed；访客无更新推送（update 不适用）。
 */
const useGuestFeedStore = defineStore('mobile_guest_feed_store', {
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
