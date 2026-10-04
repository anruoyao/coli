import { defineStore } from 'pinia';
import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

const usePinsStore = defineStore('mobile_pins_store', {
    deleteAware: true,
    state: function() {
        return {
            posts: []
        }
    },
    actions: {
        fetchGlobalPins: function() {
            colibriAPI().pins().getFrom('posts/global').then((response) => {
                this.posts = response.data.data;
            }).catch((error) => {
                this.posts = [];
            });
        }
    }
});

export { usePinsStore };
