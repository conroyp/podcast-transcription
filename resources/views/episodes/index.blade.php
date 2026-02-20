<x-layouts.app title="Episodes">
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Flash Messages -->
        @if(session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md shadow-sm mb-4 flash-message transition-opacity duration-500">
                <p>{{ session('success') }}</p>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md shadow-sm mb-4 flash-message transition-opacity duration-500">
                <p>{{ session('error') }}</p>
            </div>
        @endif

        <!-- Header -->
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow p-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Episodes</h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Manage podcast episodes and their processing status</p>
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Total Episodes: {{ $episodes->total() }}
                    @if($episodes->hasPages())
                        <span class="text-xs">(showing {{ $episodes->firstItem() }}-{{ $episodes->lastItem() }})</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Episodes Table -->
        <div class="bg-white dark:bg-neutral-800 rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-neutral-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Episode
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Published
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Download
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Transcription
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Segments
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-neutral-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($episodes as $episode)
                            <tr class="hover:bg-gray-50 dark:hover:bg-neutral-700 cursor-pointer transition-colors duration-150"
                                onclick="window.location.href='{{ route('episodes.show', $episode) }}'">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $episode->title }}
                                                </div>
                                                @if($episode->episode_type === 'midweek_mayhem')
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                        Midweek Mayhem
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                        Interview
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ $episode->podcast->title ?? 'Unknown Podcast' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        {{ $episode->published_at->format('M j, Y') }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $episode->published_at->format('g:i A') }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $downloadStatusColors = [
                                            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                            'downloading' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                            'failed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                            'pending' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
                                        ];
                                        $downloadClass = $downloadStatusColors[$episode->download_status] ?? $downloadStatusColors['pending'];
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $downloadClass }}">
                                        {{ ucfirst($episode->download_status) }}
                                    </span>
                                    @if($episode->file_size_bytes)
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            {{ number_format($episode->file_size_bytes / 1024 / 1024, 1) }} MB
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $transcriptionStatusColors = [
                                            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                            'processing' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                            'failed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                            'pending' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
                                        ];
                                        $transcriptionClass = $transcriptionStatusColors[$episode->transcription_status] ?? $transcriptionStatusColors['pending'];
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $transcriptionClass }}">
                                        {{ ucfirst($episode->transcription_status) }}
                                    </span>
                                    @if($episode->transcription_status === 'processing')
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            <div class="flex items-center">
                                                <svg class="animate-spin -ml-1 mr-1 h-3 w-3 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                In progress
                                            </div>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        {{ number_format($episode->transcript_segments_count) }} segments
                                    </div>
                                    @if($episode->embeddings_count > 0)
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ number_format($episode->embeddings_count) }} with embeddings
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex space-x-2" onclick="event.stopPropagation()">
                                        <form action="{{ route('episodes.destroy', $episode) }}" method="POST" class="delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button"
                                                class="delete-button text-sm px-3 py-1 bg-red-100 hover:bg-red-200 text-red-800 dark:bg-red-900 dark:hover:bg-red-800 dark:text-red-200 rounded transition-colors duration-150"
                                                data-episode-id="{{ $episode->id }}"
                                                data-episode-title="{{ $episode->title }}">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <div class="text-gray-500 dark:text-gray-400">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No episodes</h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No podcast episodes have been ingested yet.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Status Summary -->
        @if($episodes->count() > 0)
            <div class="bg-white dark:bg-neutral-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Processing Summary</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @php
                        $downloadStats = $episodes->countBy('download_status');
                        $transcriptionStats = $episodes->countBy('transcription_status');
                        $totalSegments = $episodes->sum('transcript_segments_count');
                        $totalEmbeddings = $episodes->sum('embeddings_count');
                    @endphp

                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                            {{ $downloadStats['completed'] ?? 0 }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Downloaded</div>
                    </div>

                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                            {{ $transcriptionStats['completed'] ?? 0 }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Transcribed</div>
                    </div>

                    <div class="text-center">
                        <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                            {{ number_format($totalSegments) }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Total Segments</div>
                    </div>

                    <div class="text-center">
                        <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                            {{ number_format($totalEmbeddings) }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Embeddings</div>
                    </div>
                </div>
            </div>
        @endif
        <!-- Pagination -->
        <div class="mt-4">
            {{ $episodes->links() }}
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 items-center justify-center z-50 hidden" style="display: none;">
        <div class="bg-white dark:bg-neutral-800 rounded-lg max-w-md w-full p-6 shadow-xl">
            <div class="text-center">
                <svg class="mx-auto h-12 w-12 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Delete Episode</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Are you sure you want to delete this episode? All transcript segments will also be deleted.
                    This action cannot be undone.
                </p>
                <p class="mt-2 font-medium" id="deleteModalTitle"></p>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button id="cancelDelete" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-md transition-colors duration-150">
                    Cancel
                </button>
                <button id="confirmDelete" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md transition-colors duration-150">
                    Delete
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('.delete-button');
            const deleteModal = document.getElementById('deleteModal');
            const cancelDelete = document.getElementById('cancelDelete');
            const confirmDelete = document.getElementById('confirmDelete');
            const deleteModalTitle = document.getElementById('deleteModalTitle');
            let currentForm = null;

            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Store the form for later submission
                    currentForm = this.closest('form');

                    // Set the episode title in the modal
                    const episodeTitle = this.dataset.episodeTitle;
                    deleteModalTitle.textContent = episodeTitle;

                    // Show the modal
                    deleteModal.classList.remove('hidden');
                    deleteModal.style.display = 'flex';
                });
            });

            // Close modal when clicking cancel
            cancelDelete.addEventListener('click', function() {
                deleteModal.classList.add('hidden');
                deleteModal.style.display = 'none';
                currentForm = null;
            });

            // Submit the form when clicking confirm
            confirmDelete.addEventListener('click', function() {
                if (currentForm) {
                    currentForm.submit();
                }
                deleteModal.classList.add('hidden');
                deleteModal.style.display = 'none';
            });

            // Close modal when clicking outside
            deleteModal.addEventListener('click', function(e) {
                if (e.target === deleteModal) {
                    deleteModal.classList.add('hidden');
                    deleteModal.style.display = 'none';
                    currentForm = null;
                }
            });

            // Flash messages fade out
            const flashMessages = document.querySelectorAll('.flash-message');
            if (flashMessages.length > 0) {
                setTimeout(() => {
                    flashMessages.forEach(message => {
                        message.classList.add('opacity-0');
                        setTimeout(() => {
                            message.remove();
                        }, 500);
                    });
                }, 3000);
            }
        });
    </script>
</x-layouts.app>
