<x-layouts.app :title="'Episode: ' . $episode->title">
    <div class="max-w-6xl mx-auto space-y-6">
        <!-- Episode Selector -->
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow p-6">
            <label for="episode-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Select Episode
            </label>
            <select id="episode-select"
                    class="w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-neutral-700 dark:text-white"
                    onchange="window.location.href = '/episodes/' + this.value">
                @foreach($allEpisodes as $ep)
                    <option value="{{ $ep->id }}" {{ $ep->id === $episode->id ? 'selected' : '' }}>
                        {{ $ep->published_at->format('Y-m-d') }} - {{ $ep->title }}
                    </option>
                @endforeach
            </select>
        </div>

        <!-- Episode Details -->
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow p-6">
            <div class="flex justify-between items-start mb-4">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $episode->title }}</h1>

                <div class="flex gap-2">
                    <!-- Re-transcribe Button -->
                    <button id="re-transcribe-btn"
                            x-data=""
                            x-on:click="$dispatch('open-modal', 're-transcribe-confirm')"
                            class="bg-orange-600 hover:bg-orange-700 disabled:bg-gray-400 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        <span class="button-text">Re-transcribe</span>
                    </button>

                    <!-- Re-evaluate Embeddings Button -->
                    <button id="re-evaluate-btn"
                            onclick="reEvaluateEmbeddings()"
                            class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span class="button-text">Re-evaluate Embeddings</span>
                    </button>

                    <!-- Sync to Server Button -->
                    <button id="sync-btn"
                            onclick="syncEpisodeToServer()"
                            class="bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <span class="button-text">Sync to Server</span>
                    </button>
                </div>
            </div>

            <!-- Processing Status Display -->
            <div class="mb-4 p-4 bg-gray-50 dark:bg-neutral-700 rounded-lg">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Processing Status</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Download:</span>
                        <span class="font-medium ml-1" data-status="download">
                            @if($episode->download_status === 'completed')
                                <span class="text-green-600">✓ Completed</span>
                            @elseif($episode->download_status === 'downloading')
                                <span class="text-yellow-600">⏳ Downloading</span>
                            @elseif($episode->download_status === 'failed')
                                <span class="text-red-600">✗ Failed</span>
                            @else
                                <span class="text-gray-600">⏸ Pending</span>
                            @endif
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Transcription:</span>
                        <span class="font-medium ml-1" data-status="transcription">
                            @if($episode->transcription_status === 'completed')
                                <span class="text-green-600">✓ Completed</span>
                            @elseif($episode->transcription_status === 'processing')
                                <span class="text-yellow-600">⏳ Processing</span>
                            @elseif($episode->transcription_status === 'failed')
                                <span class="text-red-600">✗ Failed</span>
                            @else
                                <span class="text-gray-600">⏸ Pending</span>
                            @endif
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Embeddings:</span>
                        <span class="font-medium ml-1" data-status="embedding">
                            @if($episode->embedding_status === 'completed')
                                <span class="text-green-600">✓ Completed</span>
                            @elseif($episode->embedding_status === 'processing')
                                <span class="text-yellow-600">⏳ Processing</span>
                            @elseif($episode->embedding_status === 'failed')
                                <span class="text-red-600">✗ Failed</span>
                            @elseif($episode->embedding_status === 'partial')
                                <span class="text-orange-600">⚠ Partial</span>
                            @else
                                <span class="text-gray-600">⏸ Pending</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            <!-- Embedding Status Display -->
            <div class="mb-6 p-4 bg-gray-50 dark:bg-neutral-700 rounded-lg">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Embedding Status</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Total Segments:</span>
                        <span class="font-medium text-gray-900 dark:text-white ml-1" data-stat="total">{{ $embeddingStats['total_segments'] }}</span>
                    </div>
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Embedded:</span>
                        <span class="font-medium text-green-600 ml-1" data-stat="embedded">{{ $embeddingStats['embedded_segments'] }}</span>
                    </div>
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Pending:</span>
                        <span class="font-medium text-yellow-600 ml-1" data-stat="pending">{{ $embeddingStats['pending_segments'] }}</span>
                    </div>
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Completion:</span>
                        <span class="font-medium text-blue-600 ml-1" data-stat="completion">{{ $embeddingStats['completion_rate'] }}%</span>
                    </div>
                </div>

                @if($embeddingStats['failed_segments'] > 0)
                    <div class="mt-2 text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Failed:</span>
                        <span class="font-medium text-red-600 ml-1">{{ $embeddingStats['failed_segments'] }}</span>
                    </div>
                @endif
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                        <strong>Podcast:</strong> {{ $episode->podcast->title }}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                        <strong>Published:</strong> {{ $episode->published_at->format('F j, Y g:i A') }}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        <strong>Duration:</strong> {{ $episode->duration_seconds ? gmdate('H:i:s', $episode->duration_seconds) : 'Unknown' }}
                    </p>

                    @if($episode->description)
                        <div class="prose dark:prose-invert">
                            <h3 class="text-lg font-semibold mb-2">Description</h3>
                            <p class="text-gray-700 dark:text-gray-300">{{ $episode->description }}</p>
                        </div>
                    @endif
                </div>

                <div>
                    @if($episode->audio_url)
                        <h3 class="text-lg font-semibold mb-2">Audio</h3>
                        <audio controls class="w-full">
                            <source src="{{ $episode->audio_url }}" type="audio/mpeg">
                            Your browser does not support the audio element.
                        </audio>
                    @else
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-md p-4">
                            <p class="text-yellow-800 dark:text-yellow-200">Audio URL not available</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Transcriptions -->
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Transcriptions</h2>

                @if($transcriptSegments->count() > 0)
                    <div class="flex items-center gap-3">
                        <label for="sort-select" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Sort by:
                        </label>
                        <select id="sort-select"
                                class="rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-neutral-700 dark:text-white">
                            <option value="time">Time (Sequential)</option>
                            <option value="confidence">Confidence (Low to High)</option>
                            <option value="confidence-desc">Confidence (High to Low)</option>
                        </select>
                    </div>
                @endif
            </div>

            @if($transcriptSegments->count() > 0)
                <div class="space-y-4" id="transcript-container">
                    @foreach($transcriptSegments as $segment)
                        <div class="transcript-segment border border-gray-200 dark:border-gray-600 rounded-lg p-4 transition-all duration-300"
                             data-segment-id="{{ $segment->id }}"
                             data-start-time="{{ $segment->start_time }}"
                             data-confidence="{{ $segment->confidence ?? 0 }}">
                            <div class="grid grid-cols-12 gap-4 items-start">
                                <!-- Timestamp -->
                                <div class="col-span-2">
                                    <div class="text-sm font-mono text-gray-600 dark:text-gray-400">
                                        {{ gmdate('H:i:s', $segment->start_time) }}
                                    </div>
                                    @if($segment->confidence)
                                        <div class="text-xs text-gray-500 dark:text-gray-500">
                                            {{ round($segment->confidence * 100, 1) }}%
                                        </div>
                                    @endif
                                    <!-- Audio Play Button -->
                                    <button class="play-segment-btn mt-2 bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded-md text-xs font-medium transition-colors flex items-center gap-1"
                                            data-start-time="{{ $segment->start_time }}"
                                            title="Play audio from {{ gmdate('H:i:s', max(0, $segment->start_time - 2)) }}">
                                        <svg class="play-icon w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M8 5v10l8-5-8-5z"/>
                                        </svg>
                                        <svg class="pause-icon w-3 h-3 hidden" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M6 4h2v12H6V4zm6 0h2v12h-2V4z"/>
                                        </svg>
                                        <span class="button-text">Play</span>
                                    </button>
                                </div>

                                <!-- Transcript Text -->
                                <div class="col-span-8">
                                    <textarea class="transcript-text w-full min-h-[80px] rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-neutral-700 dark:text-white resize-vertical"
                                              data-original="{{ $segment->text }}">{{ $segment->text }}</textarea>
                                </div>

                                <!-- Actions -->
                                <div class="col-span-2 flex flex-col gap-2">
                                    <button class="edit-btn bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-md text-sm font-medium transition-colors"
                                            data-segment-id="{{ $segment->id }}">
                                        Edit
                                    </button>
                                    <button class="delete-btn bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-md text-sm font-medium transition-colors"
                                            data-segment-id="{{ $segment->id }}"
                                            x-data=""
                                            x-on:click="deleteSegmentId = $el.dataset.segmentId; $dispatch('open-modal', 'delete-segment-confirm')">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <p class="text-gray-500 dark:text-gray-400">No transcript segments found for this episode.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Success Toast -->
    <div id="success-toast" class="fixed top-4 right-4 bg-green-600 text-white px-6 py-3 rounded-md shadow-lg transform translate-x-full transition-transform duration-300">
        <span id="toast-message">Success!</span>
    </div>

    <!-- Re-transcribe Confirmation Modal -->
    <flux:modal name="re-transcribe-confirm" focusable class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Confirm Re-transcription</flux:heading>
                <flux:subheading>
                    This will delete all existing transcriptions and re-transcribe the entire episode using the advanced transcription engine.
                    This process may take up to 1 hour to complete and cannot be undone.
                </flux:subheading>
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="filled">Cancel</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" x-on:click="$dispatch('close-modal', 're-transcribe-confirm'); executeReTranscribe()">
                    Re-transcribe
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Delete Segment Confirmation Modal -->
    <flux:modal name="delete-segment-confirm" focusable class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Confirm Deletion</flux:heading>
                <flux:subheading>
                    Are you sure you want to delete this transcript segment? This action cannot be undone.
                </flux:subheading>
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="filled">Cancel</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" x-on:click="$dispatch('close-modal', 'delete-segment-confirm'); confirmDeleteSegment()">
                    Delete
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <script>
        // CSRF token for Ajax requests
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        console.log('CSRF Token:', csrfToken);

        // Track the segment ID pending deletion
        let deleteSegmentId = null;

        // Initialize page - check current embedding status
        document.addEventListener('DOMContentLoaded', function() {
            checkInitialEmbeddingStatus();
        });

        // Check current embedding status on page load
        async function checkInitialEmbeddingStatus() {
            try {
                const response = await fetch(`/episodes/{{ $episode->id }}/embedding-progress`);
                const data = await response.json();

                if (data.success) {
                    const stats = data.stats;

                    // Update the status display on the page
                    updateEmbeddingStatsDisplay(stats);

                    // Handle embedding button state based on current status
                    const embedButton = document.getElementById('re-evaluate-btn');
                    const embedButtonText = embedButton.querySelector('.button-text');

                    // Handle transcription button state
                    const transcribeButton = document.getElementById('re-transcribe-btn');
                    const transcribeButtonText = transcribeButton.querySelector('.button-text');

                    // Check if transcription is processing
                    @if($episode->transcription_status === 'processing')
                        transcribeButton.disabled = true;
                        transcribeButtonText.textContent = 'Transcribing...';
                        startTranscriptionProgressPolling();
                    @elseif($episode->download_status === 'downloading')
                        transcribeButton.disabled = true;
                        transcribeButtonText.textContent = 'Downloading...';
                        startTranscriptionProgressPolling();
                    @endif

                    // Handle embedding status
                    if (stats.is_processing) {
                        embedButton.disabled = true;
                        embedButtonText.textContent = `Processing... (${stats.embedded_segments}/${stats.total_segments})`;
                        startProgressPolling(); // Continue polling if already processing
                    }
                }
            } catch (error) {
                console.error('Error checking initial embedding status:', error);
            }
        }

        // Re-evaluate embeddings for this episode
        async function reEvaluateEmbeddings() {
            const button = document.getElementById('re-evaluate-btn');
            const buttonText = button.querySelector('.button-text');
            const originalText = buttonText.textContent;

            // Disable button and show loading state
            button.disabled = true;
            buttonText.textContent = 'Queueing job...';

            try {
                const response = await fetch(`/episodes/{{ $episode->id }}/re-evaluate-embeddings`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });

                const data = await response.json();

                if (data.success) {
                    showToast(`✅ Embeddings re-evaluation job queued! Processing ${data.segment_count} segments...`);
                    buttonText.textContent = 'Processing...';

                    // Start polling for progress
                    startProgressPolling();
                } else {
                    showToast(`❌ Error: ${data.error}`);
                    button.disabled = false;
                    buttonText.textContent = originalText;
                }
            } catch (error) {
                console.error('Error queuing re-evaluation job:', error);
                showToast('❌ Network error occurred');
                button.disabled = false;
                buttonText.textContent = originalText;
            }
        }

        // Start polling for progress updates
        function startProgressPolling() {
            const progressInterval = setInterval(async () => {
                try {
                    const response = await fetch(`/episodes/{{ $episode->id }}/embedding-progress`);
                    const data = await response.json();

                    if (data.success) {
                        const stats = data.stats;

                        // Update the status display on the page
                        updateEmbeddingStatsDisplay(stats);

                        // Update button text with progress
                        const button = document.getElementById('re-evaluate-btn');
                        const buttonText = button.querySelector('.button-text');

                        if (stats.is_processing) {
                            buttonText.textContent = `Processing... (${stats.embedded_segments}/${stats.total_segments})`;
                        } else if (stats.is_completed) {
                            clearInterval(progressInterval);
                            showToast(`✅ Embeddings completed! ${stats.embedded_segments}/${stats.total_segments} segments processed (${stats.completion_rate}% success)`);
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else if (stats.is_failed) {
                            clearInterval(progressInterval);
                            showToast(`❌ Embeddings failed. Check logs for details.`);
                            button.disabled = false;
                            buttonText.textContent = 'Re-evaluate Embeddings';
                        } else if (stats.is_partial) {
                            clearInterval(progressInterval);
                            showToast(`⚠️ Embeddings partially completed. ${stats.embedded_segments}/${stats.total_segments} segments processed (${stats.completion_rate}% success)`);
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        }
                    }
                } catch (error) {
                    console.error('Error fetching embedding progress:', error);
                }
            }, 2000); // Poll every 2 seconds
        }

        // Update the embedding stats display on the page
        function updateEmbeddingStatsDisplay(stats) {
            // Find and update the stats elements if they exist
            const totalEl = document.querySelector('[data-stat="total"]');
            const embeddedEl = document.querySelector('[data-stat="embedded"]');
            const pendingEl = document.querySelector('[data-stat="pending"]');
            const completionEl = document.querySelector('[data-stat="completion"]');

            if (totalEl) totalEl.textContent = stats.total_segments;
            if (embeddedEl) embeddedEl.textContent = stats.embedded_segments;
            if (pendingEl) pendingEl.textContent = stats.pending_segments + stats.processing_segments;
            if (completionEl) completionEl.textContent = stats.completion_rate + '%';
        }

        // Sync episode to server
        async function syncEpisodeToServer() {
            const button = document.getElementById('sync-btn');
            const buttonText = button.querySelector('.button-text');
            const originalText = buttonText.textContent;

            // Disable button and show loading state
            button.disabled = true;
            buttonText.textContent = 'Queueing...';

            try {
                const response = await fetch(`/episodes/{{ $episode->id }}/sync`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });

                const data = await response.json();

                if (data.success) {
                    showToast(data.message);
                    buttonText.textContent = 'Syncing...';

                    // Start polling for sync progress
                    startSyncProgressPolling();
                } else {
                    showToast(`❌ Sync failed: ${data.error}`);
                    button.disabled = false;
                    buttonText.textContent = originalText;
                }
            } catch (error) {
                console.error('Error syncing episode:', error);
                showToast('❌ Network error occurred during sync');
                button.disabled = false;
                buttonText.textContent = originalText;
            }
        }

        // Start polling for sync progress updates
        function startSyncProgressPolling() {
            const button = document.getElementById('sync-btn');
            const buttonText = button.querySelector('.button-text');
            const originalText = 'Sync to Server';

            const progressInterval = setInterval(async () => {
                try {
                    const response = await fetch(`/episodes/{{ $episode->id }}/sync-status`);
                    const data = await response.json();

                    if (data.success) {
                        const status = data.status;
                        const progress = data.progress;

                        if (status === 'queued') {
                            buttonText.textContent = 'Queued...';
                        } else if (status === 'syncing') {
                            if (progress.phase === 'metadata') {
                                buttonText.textContent = 'Syncing metadata...';
                            } else if (progress.phase === 'segments') {
                                buttonText.textContent = `Syncing (${progress.current_batch}/${progress.total_batches})...`;
                            } else {
                                buttonText.textContent = 'Syncing...';
                            }
                        } else if (status === 'completed') {
                            clearInterval(progressInterval);
                            showToast('✅ Episode synced successfully to server!');
                            button.disabled = false;
                            buttonText.textContent = originalText;
                        } else if (status === 'failed') {
                            clearInterval(progressInterval);
                            const error = progress.error || 'Unknown error';
                            showToast(`❌ Sync failed: ${error}`);
                            button.disabled = false;
                            buttonText.textContent = originalText;
                        }
                    }
                } catch (error) {
                    console.error('Error fetching sync progress:', error);
                }
            }, 2000); // Poll every 2 seconds
        }

        // Execute re-transcription after modal confirmation
        async function executeReTranscribe() {
            const button = document.getElementById('re-transcribe-btn');
            const buttonText = button.querySelector('.button-text');
            const originalText = buttonText.textContent;

            // Disable button and show loading state
            button.disabled = true;
            buttonText.textContent = 'Queueing job...';

            try {
                const response = await fetch(`/episodes/{{ $episode->id }}/re-transcribe`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });

                const data = await response.json();

                if (data.success) {
                    showToast(`✅ Re-transcription job queued! Processing will begin shortly...`);
                    buttonText.textContent = 'Processing...';

                    // Start polling for progress
                    startTranscriptionProgressPolling();
                } else {
                    showToast(`❌ Error: ${data.error}`);
                    button.disabled = false;
                    buttonText.textContent = originalText;
                }
            } catch (error) {
                console.error('Error queuing re-transcription job:', error);
                showToast('❌ Network error occurred');
                button.disabled = false;
                buttonText.textContent = originalText;
            }
        }

        // Start polling for transcription progress updates
        function startTranscriptionProgressPolling() {
            const progressInterval = setInterval(async () => {
                try {
                    // Check both transcription and embedding status
                    const response = await fetch(`/episodes/{{ $episode->id }}/embedding-progress`);
                    const data = await response.json();

                    if (data.success) {
                        const stats = data.stats;

                        // Update processing status indicators
                        updateProcessingStatusDisplay();

                        // Update buttons based on current status
                        const transcribeButton = document.getElementById('re-transcribe-btn');
                        const transcribeButtonText = transcribeButton.querySelector('.button-text');

                        const embedButton = document.getElementById('re-evaluate-btn');
                        const embedButtonText = embedButton.querySelector('.button-text');

                        // Check episode status to determine what's happening
                        const currentEpisode = await getCurrentEpisodeStatus();

                        if (currentEpisode.transcription_status === 'processing') {
                            transcribeButtonText.textContent = 'Transcribing...';
                        } else if (currentEpisode.transcription_status === 'completed' && stats.is_processing) {
                            transcribeButtonText.textContent = 'Generating embeddings...';
                        } else if (currentEpisode.transcription_status === 'completed' && stats.is_completed) {
                            clearInterval(progressInterval);
                            showToast(`✅ Re-transcription and embedding completed! ${stats.embedded_segments}/${stats.total_segments} segments processed`);
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else if (currentEpisode.transcription_status === 'failed') {
                            clearInterval(progressInterval);
                            showToast(`❌ Re-transcription failed. Check logs for details.`);
                            transcribeButton.disabled = false;
                            transcribeButtonText.textContent = 'Re-transcribe';
                        }
                    }
                } catch (error) {
                    console.error('Error fetching transcription progress:', error);
                }
            }, 3000); // Poll every 3 seconds for transcription (slower than embedding)
        }

        // Get current episode status
        async function getCurrentEpisodeStatus() {
            try {
                const response = await fetch(`/episodes/{{ $episode->id }}/embedding-progress`);
                const data = await response.json();
                return {
                    transcription_status: data.stats.episode_status || 'unknown',
                    download_status: 'unknown' // We'll need to add this to the API if needed
                };
            } catch (error) {
                console.error('Error fetching episode status:', error);
                return { transcription_status: 'unknown', download_status: 'unknown' };
            }
        }

        // Update processing status display
        function updateProcessingStatusDisplay() {
            // This would update the processing status indicators
            // For now, we'll rely on page refresh for complete status update
            // Could be enhanced to update the status indicators in real-time
        }

        // Show toast notification
        function showToast(message) {
            const toast = document.getElementById('success-toast');
            const messageEl = document.getElementById('toast-message');
            messageEl.textContent = message;
            toast.classList.remove('translate-x-full');

            setTimeout(() => {
                toast.classList.add('translate-x-full');
            }, 3000);
        }

        // Handle edit button clicks
        document.addEventListener('click', function(e) {
            console.log('Click detected on:', e.target);

            if (e.target.classList.contains('edit-btn')) {
                console.log('Edit button clicked');
                const segmentId = e.target.dataset.segmentId;
                const segment = document.querySelector(`[data-segment-id="${segmentId}"]`);
                const textarea = segment.querySelector('.transcript-text');
                const newText = textarea.value.trim();

                console.log('Segment ID:', segmentId, 'New text:', newText);

                if (!newText) {
                    showToast('❌ Transcript text cannot be empty');
                    return;
                }

                // Disable button during request
                e.target.disabled = true;
                e.target.textContent = 'Saving...';

                fetch(`/transcript-segments/${segmentId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ text: newText })
                })
                .then(response => {
                    console.log('Response:', response);
                    return response.json();
                })
                .then(data => {
                    console.log('Data:', data);
                    if (data.success) {
                        showToast(data.message);
                        textarea.dataset.original = newText;
                    } else {
                        showToast('❌ Error updating transcript');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('❌ Error updating transcript');
                })
                .finally(() => {
                    e.target.disabled = false;
                    e.target.textContent = 'Edit';
                });
            }
        });

        // Execute segment deletion after modal confirmation
        function confirmDeleteSegment() {
            if (!deleteSegmentId) {
                return;
            }

            const segment = document.querySelector(`[data-segment-id="${deleteSegmentId}"]`);
            const segmentIdToDelete = deleteSegmentId;
            deleteSegmentId = null;

            fetch(`/transcript-segments/${segmentIdToDelete}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Fade out and remove the segment
                    segment.style.opacity = '0';
                    segment.style.transform = 'translateX(-100%)';

                    setTimeout(() => {
                        segment.remove();
                    }, 300);

                    showToast(data.message);
                } else {
                    showToast('❌ Error deleting transcript segment');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('❌ Error deleting transcript segment');
            });
        }

        // Audio segment playback functionality
        let currentlyPlayingButton = null;
        const audioPlayer = document.querySelector('audio');

        // Handle play segment button clicks
        document.addEventListener('click', function(e) {
            if (e.target.closest('.play-segment-btn')) {
                const button = e.target.closest('.play-segment-btn');
                const startTime = parseInt(button.dataset.startTime);

                if (!audioPlayer) {
                    showToast('❌ Audio player not available');
                    return;
                }

                // If this button is currently playing, pause
                if (currentlyPlayingButton === button && !audioPlayer.paused) {
                    audioPlayer.pause();
                    return;
                }

                // Reset any other playing button
                if (currentlyPlayingButton && currentlyPlayingButton !== button) {
                    resetPlayButton(currentlyPlayingButton);
                }

                // Set the new current button
                currentlyPlayingButton = button;

                // Calculate playback time (2 seconds before, but not negative)
                const playbackTime = Math.max(0, startTime - 2);

                // Set the audio time and play
                audioPlayer.currentTime = playbackTime;
                audioPlayer.play()
                    .then(() => {
                        updatePlayButton(button, true);
                    })
                    .catch(error => {
                        console.error('Error playing audio:', error);
                        showToast('❌ Error playing audio. Make sure the audio file is accessible.');
                        resetPlayButton(button);
                        currentlyPlayingButton = null;
                    });
            }
        });

        // Listen for audio events to update button states
        if (audioPlayer) {
            audioPlayer.addEventListener('play', function() {
                if (currentlyPlayingButton) {
                    updatePlayButton(currentlyPlayingButton, true);
                }
            });

            audioPlayer.addEventListener('pause', function() {
                if (currentlyPlayingButton) {
                    updatePlayButton(currentlyPlayingButton, false);
                }
            });

            audioPlayer.addEventListener('ended', function() {
                if (currentlyPlayingButton) {
                    resetPlayButton(currentlyPlayingButton);
                    currentlyPlayingButton = null;
                }
            });
        }

        // Update play button appearance
        function updatePlayButton(button, isPlaying) {
            const playIcon = button.querySelector('.play-icon');
            const pauseIcon = button.querySelector('.pause-icon');
            const buttonText = button.querySelector('.button-text');

            if (isPlaying) {
                playIcon.classList.add('hidden');
                pauseIcon.classList.remove('hidden');
                buttonText.textContent = 'Pause';
                button.classList.remove('bg-green-600', 'hover:bg-green-700');
                button.classList.add('bg-orange-600', 'hover:bg-orange-700');
            } else {
                playIcon.classList.remove('hidden');
                pauseIcon.classList.add('hidden');
                buttonText.textContent = 'Play';
                button.classList.remove('bg-orange-600', 'hover:bg-orange-700');
                button.classList.add('bg-green-600', 'hover:bg-green-700');
            }
        }

        // Reset play button to initial state
        function resetPlayButton(button) {
            updatePlayButton(button, false);
        }

        // Transcript sorting functionality
        const sortSelect = document.getElementById('sort-select');
        const transcriptContainer = document.getElementById('transcript-container');

        if (sortSelect && transcriptContainer) {
            sortSelect.addEventListener('change', function() {
                const sortBy = this.value;
                const segments = Array.from(transcriptContainer.querySelectorAll('.transcript-segment'));

                // Sort segments based on selected criteria
                segments.sort((a, b) => {
                    switch (sortBy) {
                        case 'time':
                            return parseInt(a.dataset.startTime) - parseInt(b.dataset.startTime);

                        case 'confidence':
                            const confA = parseFloat(a.dataset.confidence) || 0;
                            const confB = parseFloat(b.dataset.confidence) || 0;
                            return confA - confB; // Low to high

                        case 'confidence-desc':
                            const confADesc = parseFloat(a.dataset.confidence) || 0;
                            const confBDesc = parseFloat(b.dataset.confidence) || 0;
                            return confBDesc - confADesc; // High to low

                        default:
                            return 0;
                    }
                });

                // Clear container and re-append sorted segments
                transcriptContainer.innerHTML = '';
                segments.forEach(segment => {
                    transcriptContainer.appendChild(segment);
                });

                // Add visual indication of sorting
                showToast(`Sorted by ${sortBy === 'time' ? 'time order' :
                          sortBy === 'confidence' ? 'confidence (low to high)' :
                          'confidence (high to low)'}`);
            });
        }
    </script>
</x-layouts.app>
