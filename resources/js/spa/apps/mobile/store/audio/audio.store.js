import { defineStore } from 'pinia';

const useAudioStore = defineStore('mobile_audio_store', {
    state: function() {
		return {
			audioData: null,
            playerState: {}
		}
	},
    actions: {
        add: function(audioData) {
            if(this.audioData === null || (this.audioData !== null && this.audioData.id !== audioData.id)) {
                this.audioData = audioData;
                this.refreshState();
            }
        },
        remove: function() {
            this.audioData = null;
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