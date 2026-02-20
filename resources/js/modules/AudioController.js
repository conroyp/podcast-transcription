export class AudioController {
    constructor() {
        this.currentAudio = null;
        this.sidebarPlayer = null;
        this.audioTimeUpdateHandler = null;
        this.onTimeUpdateCallback = null;
    }

    set onTimeUpdate(callback) {
        this.onTimeUpdateCallback = callback;
    }

    loadAudio(episodeId) {
        const audio = document.getElementById('episodeAudio');
        const audioSource = document.getElementById('audioSource');

        if (!audio || !audioSource) return;

        // Set audio source
        audioSource.src = `/episode/${episodeId}/audio`;
        audio.load();

        // Reinitialize Plyr for the sidebar if it exists
        if (this.sidebarPlayer) {
            this.sidebarPlayer.destroy();
            this.sidebarPlayer = null;
        }

        // Wait a moment for the audio to load, then reinitialize Plyr
        setTimeout(() => {
            if (audio && !this.sidebarPlayer && window.Plyr) {
                this.sidebarPlayer = new window.Plyr(audio, {
                    controls: [
                        'play', 'progress', 'current-time', 'duration',
                        'mute', 'volume', 'settings'
                    ],
                    seekTime: 15,
                    displayDuration: true,
                    storage: { enabled: true, key: 'plyr_sidebar' },
                    speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
                    keyboard: { focused: false, global: false },
                    tooltips: { controls: true, seek: true }
                });

                this.setupSidebarPlayerEvents();
            } else if (audio && !window.Plyr) {
                this.setupFallbackAudioEvents(audio);
            }
        }, 200);
    }

    setupFallbackAudioEvents(audio) {
        audio.addEventListener('canplay', () => {
            this.currentAudio = audio;
            this.setupAudioTimeTracking();
            // Dispatch event or callback that audio is ready
            if (this.onAudioReady) this.onAudioReady(this.currentAudio);
        });
    }

    setupSidebarPlayerEvents() {
        const player = this.sidebarPlayer;
        if (!player) return;

        player.on('canplay', () => {
            this.currentAudio = player;
            this.setupAudioTimeTracking();
            if (this.onAudioReady) this.onAudioReady(player);
        });

        player.on('ready', () => {
            if (this.onAudioReady) this.onAudioReady(player);
        });
    }

    setupAudioTimeTracking() {
        if (!this.currentAudio) return;

        const isPlyr = !!this.sidebarPlayer;

        // Remove existing listener if it exists
        if (this.audioTimeUpdateHandler) {
            if (isPlyr) {
                this.currentAudio.off('timeupdate', this.audioTimeUpdateHandler);
            } else {
                this.currentAudio.removeEventListener('timeupdate', this.audioTimeUpdateHandler);
            }
        }

        // Create new handler function
        this.audioTimeUpdateHandler = () => {
            if (!this.currentAudio) return;
            const currentTime = this.currentAudio.currentTime;

            if (this.onTimeUpdateCallback) {
                this.onTimeUpdateCallback(currentTime);
            }
        };

        // Add the event listener
        if (isPlyr) {
            this.currentAudio.on('timeupdate', this.audioTimeUpdateHandler);
        } else {
            this.currentAudio.addEventListener('timeupdate', this.audioTimeUpdateHandler);
        }
    }

    play() {
        if (!this.currentAudio) return Promise.resolve();

        if (this.sidebarPlayer && this.currentAudio === this.sidebarPlayer) {
            return this.currentAudio.play();
        } else if (this.currentAudio.play) {
            return this.currentAudio.play();
        }
        return Promise.resolve();
    }

    pause() {
        if (this.currentAudio) {
            this.currentAudio.pause();
        }
    }

    seek(time) {
        if (this.currentAudio) {
            this.currentAudio.currentTime = time;
        }
    }

    cleanup() {
        this.pause();
        if (this.audioTimeUpdateHandler && this.currentAudio) {
            if (this.sidebarPlayer) {
                this.currentAudio.off('timeupdate', this.audioTimeUpdateHandler);
            } else {
                this.currentAudio.removeEventListener('timeupdate', this.audioTimeUpdateHandler);
            }
        }
        this.audioTimeUpdateHandler = null;
        this.currentAudio = null;
    }
}
