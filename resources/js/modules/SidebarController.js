export class SidebarController {
    constructor(audioController) {
        this.audioController = audioController;
        this.currentEpisodeData = null;
        this.activeSegmentId = null;
        this.targetSegmentForAutoSeek = null;
        this.manualSegmentOverride = false;

        // Bind audio controller events
        this.audioController.onTimeUpdate = (currentTime) => this.handleTimeUpdate(currentTime);
        this.audioController.onAudioReady = (player) => this.handleAutoSeekAndPlay(player);

        // Handle browser back/forward buttons
        window.addEventListener('popstate', (event) => this.handlePopState(event));
    }

    async open(episodeId, segmentId = null) {
        // Reset state variables
        this.activeSegmentId = null;
        this.manualSegmentOverride = false;

        const sidebar = document.getElementById('episodeSidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        const transcriptSegments = document.getElementById('transcriptSegments');

        if (!sidebar || !backdrop) return;

        // Check if sidebar is already populated with server-rendered content for THIS episode
        const currentEpisodeIdAttr = sidebar.getAttribute('data-current-episode-id');
        const isPrePopulated = transcriptSegments &&
                               transcriptSegments.children.length > 0 &&
                               currentEpisodeIdAttr == episodeId;

        if (isPrePopulated) {
            // Sidebar already has content from server for this exact episode - just open it
            sidebar.classList.add('open');
            backdrop.classList.add('open');

            // Update URL with episode and segment id
            this.updateUrlState(episodeId, segmentId);

            // Store episode info from pre-rendered data for modal use
            const titleEl = document.getElementById('sidebarEpisodeTitle');
            const descriptionEl = document.getElementById('episodeInfoModalContent');
            if (titleEl) {
                window.currentEpisodeInfo = {
                    id: episodeId,
                    title: titleEl.textContent.trim(),
                    description: descriptionEl ? descriptionEl.innerHTML : ''
                };
            }

            // Build currentEpisodeData from pre-rendered DOM for audio tracking
            const segments = Array.from(transcriptSegments.children).map(segmentEl => ({
                id: parseInt(segmentEl.getAttribute('data-segment-id')),
                start_time: parseFloat(segmentEl.getAttribute('data-start-time'))
            }));

            this.currentEpisodeData = {
                episode: window.currentEpisodeInfo,
                segments: segments
            };

            // Hide loading state
            this.showLoading(false);

            // Determine target segment (default to first if not provided)
            let segId = segmentId;
            if (!segId && segments.length > 0) {
                segId = segments[0].id;
            }

            // Handle segment highlighting and scrolling
            if (segId) {
                setTimeout(() => {
                    this.setActiveSegment(segId);
                    this.scrollToSegment(segId);
                }, 400);
            }

            // Store target segment for auto-seek when audio loads
            this.targetSegmentForAutoSeek = segId;

            // Initialize audio player
            this.audioController.loadAudio(episodeId);

            return;
        }

        // Need to fetch new episode data (either not pre-populated or different episode)
        // Update URL first
        this.updateUrlState(episodeId, segmentId);

        // Show sidebar immediately with loading state
        sidebar.classList.add('open');
        backdrop.classList.add('open');
        this.showLoading(true);

        try {
            const response = await fetch(`/episode/${episodeId}/segments`);
            const data = await response.json();

            if (data.success) {
                this.currentEpisodeData = data;

                // If no segmentId provided, use the first segment's id if available
                let targetSegId = segmentId;
                if (!targetSegId && data.segments && data.segments.length > 0) {
                    targetSegId = data.segments[0].id;
                }

                this.populate(data, targetSegId);

                // Update the data attribute to track the current episode
                sidebar.setAttribute('data-current-episode-id', episodeId);

                // Store target segment for auto-seek when audio loads
                this.targetSegmentForAutoSeek = targetSegId;
                this.audioController.loadAudio(episodeId);
            } else {
                this.showError('Failed to load episode data');
            }
        } catch (error) {
            console.error(error);
            this.showError('Network error occurred');
        }
    }

    close(updateHistory = true) {
        const sidebar = document.getElementById('episodeSidebar');
        const backdrop = document.getElementById('sidebarBackdrop');

        if (sidebar) sidebar.classList.remove('open');
        if (backdrop) backdrop.classList.remove('open');

        if (updateHistory) {
            // Remove episode and segment params from URL
            const url = new URL(window.location);
            url.searchParams.delete('episode');
            url.searchParams.delete('segment');
            window.history.replaceState({}, '', url);
        }

        // Clean up audio
        this.audioController.cleanup();

        // Clear episode data
        this.currentEpisodeData = null;
        this.activeSegmentId = null;
        this.targetSegmentForAutoSeek = null;
        this.manualSegmentOverride = false;
    }

    showLoading(show) {
        const loading = document.getElementById('sidebarLoading');
        const content = document.querySelector('#episodeSidebar > div:not(#sidebarLoading)');

        if (show) {
            if (loading) loading.classList.remove('hidden');
            if (content) content.style.display = 'none';
        } else {
            if (loading) loading.classList.add('hidden');
            if (content) content.style.display = 'block';
        }
    }

    showError(message) {
        const loading = document.getElementById('sidebarLoading');
        if (loading) {
            loading.innerHTML = `
                <div class="text-center">
                    <div class="text-red-500 text-6xl mb-4">⚠️</div>
                    <p class="text-red-600">${message}</p>
                    <button onclick="window.app.sidebar.close()" class="mt-4 px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">Close</button>
                </div>
            `;
        }
    }

    updateUrlState(episodeId, segmentId) {
        const url = new URL(window.location);
        if (episodeId) url.searchParams.set('episode', episodeId);
        if (segmentId) {
            url.searchParams.set('segment', segmentId);
        } else {
            url.searchParams.delete('segment');
        }
        window.history.replaceState({}, '', url);
    }

    populate(data, targetSegmentId) {
        this.showLoading(false);
        const episode = data.episode;

        // Store episode data globally for modal use (legacy support if needed)
        window.currentEpisodeInfo = episode;

        // Update URL
        this.updateUrlState(episode.id, targetSegmentId);

        // Update UI elements
        const titleEl = document.getElementById('sidebarEpisodeTitle');
        if (titleEl) titleEl.innerHTML = episode.title;

        const metaEl = document.getElementById('sidebarEpisodeMeta');
        if (metaEl) {
            const episodeType = episode.episode_type === 'midweek_mayhem' ? 'Midweek Mayhem' : 'Interview';
            metaEl.textContent = `${episode.formatted_date} • ${episodeType}`;
        }

        // Populate info modal
        const modalTitle = document.getElementById('episodeInfoModalTitle');
        const modalContent = document.getElementById('episodeInfoModalContent');
        if (modalTitle) modalTitle.textContent = episode.title;
        if (modalContent) modalContent.innerHTML = (episode.description || 'No description available.').replace(/\n{2,}/g, '<br>');

        // Populate segments
        const segmentsContainer = document.getElementById('transcriptSegments');
        if (segmentsContainer) {
            segmentsContainer.innerHTML = data.segments.map(segment => `
                <div class="segment-item p-4 border-b border-gray-100"
                     data-segment-id="${segment.id}"
                     data-start-time="${segment.start_time}"
                     onclick="window.app.sidebar.jumpToSegment(${segment.id}, ${segment.start_time})">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-xs text-blue-600 font-mono">${segment.formatted_time} - ${segment.formatted_end_time}</span>
                    </div>
                    <div class="text-sm text-gray-700 leading-relaxed">${segment.text}</div>
                </div>
            `).join('');
        }

        // Set active segment
        if (targetSegmentId) {
            setTimeout(() => {
                this.setActiveSegment(targetSegmentId);
                this.scrollToSegment(targetSegmentId);
            }, 400);
        }
    }

    handleAutoSeekAndPlay(player) {
        if (this.targetSegmentForAutoSeek && this.currentEpisodeData) {
            const targetSegment = this.currentEpisodeData.segments.find(seg => seg.id == this.targetSegmentForAutoSeek);

            if (targetSegment) {
                setTimeout(() => {
                    player.currentTime = targetSegment.start_time;
                    const playPromise = player.play ? player.play() : Promise.resolve();
                    playPromise.catch(e => console.log('Auto-play prevented:', e.message));
                }, 500);
            }
            this.targetSegmentForAutoSeek = null;
        }
    }

    handleTimeUpdate(currentTime) {
        if (this.manualSegmentOverride || !this.currentEpisodeData) return;

        const currentSegment = this.findSegmentByTime(currentTime);
        if (currentSegment && currentSegment.id !== this.activeSegmentId) {
            this.setActiveSegment(currentSegment.id);
        }
    }

    findSegmentByTime(currentTime) {
        if (!this.currentEpisodeData) return null;
        const segments = this.currentEpisodeData.segments;
        for (let i = 0; i < segments.length; i++) {
            const seg = segments[i];
            const nextSeg = segments[i + 1];
            if (nextSeg) {
                if (currentTime >= seg.start_time && currentTime < nextSeg.start_time) {
                    return seg;
                }
            } else {
                if (currentTime >= seg.start_time) {
                    return seg;
                }
            }
        }
        return null;
    }

    jumpToSegment(segmentId, startTime) {
        this.manualSegmentOverride = true;

        // Update URL
        if (this.currentEpisodeData && this.currentEpisodeData.episode) {
            this.updateUrlState(this.currentEpisodeData.episode.id, segmentId);
        }

        this.audioController.seek(startTime);
        this.audioController.play().catch(() => {});

        this.setActiveSegment(segmentId);

        setTimeout(() => {
            this.manualSegmentOverride = false;
        }, 1000);
    }

    setActiveSegment(segmentId) {
        if (this.activeSegmentId === segmentId) return;

        if (this.activeSegmentId) {
            const prev = document.querySelector(`.segment-item[data-segment-id="${this.activeSegmentId}"]`);
            if (prev) prev.classList.remove('active');
        }

        const next = document.querySelector(`.segment-item[data-segment-id="${segmentId}"]`);
        if (next) {
            next.classList.add('active');
            this.activeSegmentId = segmentId;
            this.scrollToSegment(segmentId);

            // Update URL to reflect current segment
            if (this.currentEpisodeData && this.currentEpisodeData.episode) {
                this.updateUrlState(this.currentEpisodeData.episode.id, segmentId);
            }
        } else {
            this.activeSegmentId = null;
        }
    }

    scrollToSegment(segmentId) {
        const segment = document.querySelector(`.segment-item[data-segment-id="${segmentId}"]`);
        if (segment) {
            try {
                segment.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
            } catch (e) {
                // Fallback omitted for brevity, modern browsers support this
            }
        }
    }

    handlePopState(event) {
        const url = new URL(window.location);
        const episodeId = url.searchParams.get('episode');
        const segmentId = url.searchParams.get('segment');

        if (episodeId) {
            // If sidebar is already open with this episode, just jump to segment if needed
            if (this.currentEpisodeData && this.currentEpisodeData.episode.id == episodeId) {
                if (segmentId && segmentId != this.activeSegmentId) {
                    const segment = this.currentEpisodeData.segments.find(s => s.id == segmentId);
                    if (segment) {
                        this.jumpToSegment(segmentId, segment.start_time);
                    }
                }
            } else {
                // Different episode, open it
                this.open(episodeId, segmentId);
            }
        } else {
            // No episode in URL, close sidebar
            this.close(false);
        }
    }
}
