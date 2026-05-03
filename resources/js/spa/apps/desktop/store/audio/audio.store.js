import { defineStore } from 'pinia';

const useAudioStore = defineStore('audio_store', {
    state: function() {
		return {
			postAudio: null,
            playerState: {}
		}
	},
    getters: {
        postAudioData: function() {
            return this.postAudio;
        }
    },
    actions: {
        add: function(postAudio) {
            if(this.postAudio === null || (this.postAudio !== null && this.postAudio.id !== postAudio.id)) {
                this.postAudio = postAudio;
                this.refreshState();
            }
        },
        remove: function() {
            this.postAudio = null;
            this.playerState = this.getState();
        },
        refreshState: function() {
            this.playerState = this.getState();
        },
        getState: function() {
            return new Object({
                playing: false,
                playbackTime: 0,
                progressBar: 0,
                isMuted: false,
                rate: 1,
                loop: false,
                isLoading: true,
                errors: []
            });
        },
        updateStateValue: function(key, value) {
            this.playerState[key] = value;
        },
        addStateError: function(error) {
            this.playerState.errors.push(error);
        },
        clearStateErrors: function() {
            this.playerState.errors = [];
        }
    }
});

export { useAudioStore };