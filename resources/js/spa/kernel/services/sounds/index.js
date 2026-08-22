import { Howl } from 'howler';

const colibriSounds = {
	sounds: embedder('config.sounds'),
	activeChatMessageReceived: function() {
		colibriSounds.playSound(colibriSounds.sounds.chat.active_chat_message_received);
	},
	backgroundChatMessageReceived: function() {
		colibriSounds.playSound(colibriSounds.sounds.chat.background_chat_message_received);
	},
	chatMessageSent: function() {
		colibriSounds.playSound(colibriSounds.sounds.chat.chat_message_sent);
	},
	notificationReceived: function() {
		colibriSounds.playSound(colibriSounds.sounds.notification.received);
	},
	uiFeedback: function() {
		colibriSounds.playSound(colibriSounds.sounds.notification.ui_feedback);
	},
	playSound: function(soundSourceUrl) {
		let sound = new Howl({
			src: [soundSourceUrl],
			volume: 0.5
		});

		sound.play();
	}
};

export { colibriSounds };