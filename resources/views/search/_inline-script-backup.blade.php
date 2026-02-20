@push('scripts')
    <script>
        // Server-side search data (if available)
        const serverSearchData = @json($searchData ?? null);

        // Server-side episode data (if available)
        const serverEpisodeData = {!! $episodeDataJson ?? 'null' !!};

        // Current view mode
        const currentView = @json($view ?? 'search');

        // Track the most recent search parameters for pagination
        let lastSearchParams = null;

        // Global variables for sidebar audio and episode management
        let currentAudio = null;
        let currentEpisodeData = null;
        let activeSegmentId = null;
        let targetSegmentForAutoSeek = null;
        let manualSegmentOverride = false;
        let audioTimeUpdateHandler = null;

        // Load stats on page load
        document.addEventListener('DOMContentLoaded', async function() {
            // Ensure the button is in the correct initial state
            initializeButton();

            // Initialize view toggle
            initializeViewToggle();

            const paginationContainer = document.getElementById('pagination');
            if (paginationContainer) {
                paginationContainer.addEventListener('click', handlePaginationClick);
            }

            // --- Check for episode/segment params for deeplink sidebar ---
            const urlParams = new URLSearchParams(window.location.search);
            const deeplinkEpisodeId = urlParams.get('episode');
            const deeplinkSegmentId = urlParams.get('segment');
            let triedDeeplinkSidebar = false;

            // Helper to try opening sidebar after main logic
            async function tryOpenDeeplinkSidebar() {
                if (triedDeeplinkSidebar) return;
                if (deeplinkEpisodeId) {
                    // If segment is not present, will open at first segment
                    await openEpisodeSidebar(deeplinkEpisodeId, deeplinkSegmentId);
                }
                triedDeeplinkSidebar = true;
            }

            // Check view mode
            if (currentView === 'episodes') {
                if (serverEpisodeData) {
                    handleServerSideEpisodes();
                } else {
                    await loadEpisodes();
                }
            } else if (serverSearchData) {
                handleServerSideResults();
            } else {
                handleUrlParameters();
            }

            await tryOpenDeeplinkSidebar();
        });

        // Initialize view toggle
        function initializeViewToggle() {
            // Set initial toggle state based on current view
            const transcriptsRadio = document.getElementById('transcripts');
            const episodesRadio = document.getElementById('episodes');

            if (currentView === 'episodes') {
                episodesRadio.checked = true;
            } else {
                transcriptsRadio.checked = true;
            }

            // Add event listeners for toggle changes
            document.querySelectorAll('input[name="view_mode"]').forEach(radio => {
                radio.addEventListener('change', async function() {
                    if (this.checked) {
                        await handleViewModeChange(this.value);
                    }
                });
            });
        }

        // Handle view mode change
        async function handleViewModeChange(newMode) {
            if (newMode === 'episodes') {
                // Switch to episodes view with AJAX
                const episodeType = document.querySelector('select[name="episode_type"]').value;
                const sort = document.querySelector('select[name="sort"]').value;
                const searchQuery = document.getElementById('searchInput').value.trim();

                // Update URL for episodes view
                const url = new URL(window.location);
                url.searchParams.set('view', 'episodes');

                if (episodeType !== 'all') {
                    url.searchParams.set('episode_type', episodeType);
                } else {
                    url.searchParams.delete('episode_type');
                }
                if (sort !== 'newest') {
                    url.searchParams.set('sort', sort);
                } else {
                    url.searchParams.delete('sort');
                }
                if (searchQuery) {
                    url.searchParams.set('q', searchQuery);
                } else {
                    url.searchParams.delete('q');
                }

                // Remove page parameter
                url.searchParams.delete('page');

                // Update URL without page reload
                window.history.pushState({}, '', url);

                // Update title based on search query
                updatePageTitle(searchQuery);

                // Load episodes via AJAX
                await loadEpisodes(1, { updateHistory: false });

            } else {
                // Switch to transcripts view
                const searchQuery = document.getElementById('searchInput').value.trim();
                const episodeType = document.querySelector('select[name="episode_type"]').value;
                const sort = document.querySelector('select[name="sort"]').value;

                // Update URL for transcript search, preserving ALL filter parameters
                const url = new URL(window.location);
                url.searchParams.delete('view');
                url.searchParams.delete('page');

                // Always preserve filter parameters regardless of search query presence
                if (episodeType !== 'all') {
                    url.searchParams.set('episode_type', episodeType);
                } else {
                    url.searchParams.delete('episode_type');
                }
                if (sort !== 'relevance') {
                    url.searchParams.set('sort', sort);
                } else {
                    url.searchParams.delete('sort');
                }

                if (searchQuery) {
                    // If there's a search query, perform transcript search
                    url.searchParams.set('q', searchQuery);
                    window.history.pushState({}, '', url);
                    updatePageTitle(searchQuery);
                    await performSearch(searchQuery, episodeType, sort, 1, { updateHistory: false });
                } else {
                    // No search query, preserve parameters but go to welcome state
                    url.searchParams.delete('q');
                    window.history.pushState({}, '', url);
                    updatePageTitle('');
                    // Clear results and show welcome state (but preserve search input)
                    clearResultsButKeepFilters();
                }
            }
        }

        // Handle server-side search results
        function handleServerSideResults() {
            if (!serverSearchData) return;

            // Populate the search form with the values used for server-side search
            if (serverSearchData.query) {
                document.getElementById('searchInput').value = serverSearchData.query;
            }
            if (serverSearchData.episode_type) {
                document.querySelector('select[name="episode_type"]').value = serverSearchData.episode_type;
                document.getElementById('formEpisodeType').value = serverSearchData.episode_type;
            }
            if (serverSearchData.sort) {
                document.querySelector('select[name="sort"]').value = serverSearchData.sort;
                document.getElementById('formSort').value = serverSearchData.sort;
            }

            // Check if results are already rendered (server-side)
            const resultsList = document.getElementById('resultsList');
            const isRendered = resultsList && resultsList.children.length > 0;

            // Display the results
            if (serverSearchData.success) {
                displayResults(serverSearchData, isRendered);
            } else {
                showError(serverSearchData.error || 'Search failed');
            }
        }

        // Handle server-side episode data
        function handleServerSideEpisodes() {
            if (!serverEpisodeData) return;

            // Populate the form filters with the values used for server-side episode loading
            if (serverEpisodeData.filters && serverEpisodeData.filters.episode_type) {
                document.querySelector('select[name="episode_type"]').value = serverEpisodeData.filters.episode_type;
            }
            if (serverEpisodeData.filters && serverEpisodeData.filters.sort) {
                document.querySelector('select[name="sort"]').value = serverEpisodeData.filters.sort;
            }
            if (serverEpisodeData.filters && serverEpisodeData.filters.q) {
                document.getElementById('searchInput').value = serverEpisodeData.filters.q;
            }

            // Check if episodes are already rendered (server-side)
            const cardsList = document.getElementById('episodeCardsList');
            if (cardsList.children.length > 0) {
                // Already rendered, just ensure container is visible
                document.getElementById('episodeCards').classList.remove('hidden');
                // Ensure welcome state is hidden
                document.getElementById('welcomeState').classList.add('hidden');
                return;
            }

            // Display the episodes
            if (serverEpisodeData.success) {
                displayEpisodeCards(serverEpisodeData);
            } else {
                showError(serverEpisodeData.error || 'Failed to load episodes');
            }
        }

        // Handle URL parameters for shareable searches (client-side only)
        function handleUrlParameters(forceSearch = false) {
            const urlParams = new URLSearchParams(window.location.search);
            const view = urlParams.get('view');
            const query = urlParams.get('q');
            const episodeType = urlParams.get('episode_type');
            const sort = urlParams.get('sort');
            const page = parseInt(urlParams.get('page') || '1', 10) || 1;

            // Handle Episodes View
            if (view === 'episodes') {
                // Ensure toggle is set correctly
                const episodesRadio = document.getElementById('episodes');
                if (episodesRadio && !episodesRadio.checked) {
                    episodesRadio.checked = true;
                }

                // Update filters
                if (episodeType) {
                    document.querySelector('select[name="episode_type"]').value = episodeType;
                    document.getElementById('formEpisodeType').value = episodeType;
                }
                if (sort) {
                    document.querySelector('select[name="sort"]').value = sort;
                    document.getElementById('formSort').value = sort;
                }
                if (query) {
                    document.getElementById('searchInput').value = query;
                } else {
                    document.getElementById('searchInput').value = '';
                }

                // Hide welcome state explicitly for episodes view
                document.getElementById('welcomeState').classList.add('hidden');

                // Update title
                updatePageTitle(query || '');

                // Load episodes
                loadEpisodes(page, { updateHistory: false });
                return;
            }

            // Ensure toggle is set to transcripts if not episodes
            const transcriptsRadio = document.getElementById('transcripts');
            if (transcriptsRadio && !transcriptsRadio.checked) {
                transcriptsRadio.checked = true;
            }

            if (query) {
                // Populate the search form
                document.getElementById('searchInput').value = query;

                const resolvedEpisodeType = episodeType || 'all';
                document.querySelector('select[name="episode_type"]').value = resolvedEpisodeType;
                document.getElementById('formEpisodeType').value = resolvedEpisodeType;

                const resolvedSort = sort || 'relevance';
                document.querySelector('select[name="sort"]').value = resolvedSort;
                document.getElementById('formSort').value = resolvedSort;

                // Update title for current query
                updatePageTitle(query);

                // Perform search if forced (back/forward navigation) or if no server-side data
                if (forceSearch || !serverSearchData) {
                    performSearch(
                        query,
                        resolvedEpisodeType,
                        resolvedSort,
                        page,
                        { updateHistory: false }
                    );
                }
            } else if (forceSearch) {
                // If no query in URL but we're forcing (back button to homepage), clear results
                clearResults();
            } else {
                // No query and not forced - show welcome state on initial page load
                document.getElementById('welcomeState').classList.remove('hidden');
            }
        }

        // Clear search results
        function clearResults() {
            document.getElementById('results').classList.add('hidden');
            document.getElementById('noResults').classList.add('hidden');
            document.getElementById('errorState').classList.add('hidden');
            document.getElementById('loadingState').classList.add('hidden');
            document.getElementById('welcomeState').classList.remove('hidden');

            document.getElementById('searchInput').value = '';
            const header = document.getElementById('resultsHeader');
            if (header) {
                header.querySelector('h2').innerHTML = '';
                header.querySelector('p').innerHTML = '';
            }
            const pagination = document.getElementById('pagination');
            if (pagination) {
                pagination.innerHTML = '';
            }
            lastSearchParams = null;

            // Clear title back to default
            updatePageTitle('');
        }

        // Clear search results but keep filters and search input
        function clearResultsButKeepFilters() {
            document.getElementById('results').classList.add('hidden');
            document.getElementById('episodeCards').classList.add('hidden');
            document.getElementById('noResults').classList.add('hidden');
            document.getElementById('errorState').classList.add('hidden');
            document.getElementById('loadingState').classList.add('hidden');
            document.getElementById('welcomeState').classList.remove('hidden');

            // Don't clear the search input or filter values
            const header = document.getElementById('resultsHeader');
            if (header) {
                header.querySelector('h2').innerHTML = '';
                header.querySelector('p').innerHTML = '';
            }
            const pagination = document.getElementById('pagination');
            if (pagination) {
                pagination.innerHTML = '';
            }
            lastSearchParams = null;
        }

        // Update page title based on query parameter
        function updatePageTitle(query = '') {
            const baseTitle = 'Everything Is Showbiz - What Did You Do Yesterday?';
            const episodesToggle = document.getElementById('episodes');
            const isEpisodesView = episodesToggle && episodesToggle.checked;

            if (isEpisodesView) {
                if (query && query.trim()) {
                    document.title = `${query.trim()} (Episodes) - Everything Is Showbiz`;
                } else {
                    document.title = `Episode Archive - Everything Is Showbiz`;
                }
            } else {
                if (query && query.trim()) {
                    document.title = `${query.trim()}: ${baseTitle}`;
                } else {
                    document.title = baseTitle;
                }
            }
        }

        // Update URL with search parameters
        function updateUrl(query, episodeType = 'all', sort = 'relevance', page = 1) {
            const url = new URL(window.location);
            if (query && query.trim()) {
                url.searchParams.set('q', query);
            } else {
                url.searchParams.delete('q');
            }

            if (episodeType && episodeType !== 'all') {
                url.searchParams.set('episode_type', episodeType);
            } else {
                url.searchParams.delete('episode_type');
            }

            if (sort && sort !== 'relevance') {
                url.searchParams.set('sort', sort);
            } else {
                url.searchParams.delete('sort');
            }

            if (page > 1) {
                url.searchParams.set('page', page);
            } else {
                url.searchParams.delete('page');
            }

            // Remove view parameter - searches always return to segment search mode
            url.searchParams.delete('view');

            // Update URL without refreshing the page
            window.history.pushState({}, '', url);

            // Update page title
            updatePageTitle(query);
        }

        // Initialize button state
        function initializeButton() {
            const button = document.querySelector('button[type="submit"]');
            const searchText = button.querySelector('.search-text');
            const loading = button.querySelector('.loading');

            // Ensure initial state is correct
            searchText.classList.remove('hidden');
            loading.classList.add('hidden');
            loading.style.display = 'none';
            button.disabled = false;
        }

        // Handle search form submission
        document.getElementById('searchForm').addEventListener('submit', async function(e) {
            const formData = new FormData(e.target);
            const query = formData.get('q').trim();
            // Get filter values directly from the dropdowns since they're outside the form
            const episodeType = document.querySelector('select[name="episode_type"]').value;
            const sort = document.querySelector('select[name="sort"]').value;


            // Update hidden form fields with current filter values
            document.getElementById('formEpisodeType').value = episodeType;
            document.getElementById('formSort').value = sort;

            // Check current view mode to determine URL update strategy
            const episodesToggle = document.getElementById('episodes');
            const currentToggleView = episodesToggle.checked ? 'episodes' : 'transcripts';

            if (!query && currentToggleView != 'episodes') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
                return;
            }

            // Update URL with search parameters for shareability
            if (currentToggleView === 'episodes') {
                // For episodes view, update URL with view parameter
                const url = new URL(window.location);
                url.searchParams.set('view', 'episodes');
                url.searchParams.set('q', query);
                url.searchParams.set('episode_type', episodeType);
                url.searchParams.set('sort', sort);
                url.searchParams.delete('page'); // Reset pagination
                window.history.pushState({}, '', url);
            } else {
                // For transcripts view, use existing updateUrl function
                updateUrl(query, episodeType, sort, 1);
            }

            // Check if we should use AJAX (modern browsers with JS enabled)
            // Allow fallback to regular form submission for accessibility/no-JS scenarios
            if (window.fetch && !e.ctrlKey && !e.metaKey) {
                // Prevent default form submission and use AJAX
                e.preventDefault();

                // Perform the AJAX search
                await performSearch(query, episodeType, sort, 1, { updateHistory: false });
            }
            // If fetch is not supported or user is holding Ctrl/Cmd, let the form submit normally
        });

        // Perform search function (can be called from form submission or URL parameters)
        async function performSearch(query, episodeType = 'all', sort = 'relevance', page = 1, options = {}) {
            const { updateHistory = false } = options;
            const resolvedPage = Math.max(parseInt(page, 10) || 1, 1);

            // Check current view mode
            const episodesToggle = document.getElementById('episodes');
            const currentToggleView = episodesToggle.checked ? 'episodes' : 'transcripts';

            // If we're in episodes view, search episodes instead of transcripts
            if (currentToggleView === 'episodes') {
                // For episodes view, we need to load episodes with search filter
                await loadEpisodes(1);
                return;
            }

            if (updateHistory) {
                updateUrl(query, episodeType, sort, resolvedPage);
            }

            // Show loading state for transcript search
            showLoadingState();

            try {
                // Build URL with query parameters for transcript search
                const url = new URL('/', window.location.origin);
                url.searchParams.set('q', query);
                url.searchParams.set('episode_type', episodeType);
                url.searchParams.set('sort', sort);
                if (resolvedPage > 1) {
                    url.searchParams.set('page', resolvedPage);
                }
                url.searchParams.set('ajax', '1'); // Explicitly request JSON response

                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();

                if (data.success) {
                    displayResults(data);
                } else {
                    showError(data.error || 'Search failed');
                }

            } catch (error) {
                showError('Network error occurred. Please try again.');
            } finally {
                // Always hide loading state, even if there's an unexpected error
                hideLoadingState();
            }
        }

        function showLoadingState(message = 'Searching yesterdays...') {
            document.getElementById('results').classList.add('hidden');
            document.getElementById('episodeCards').classList.add('hidden');
            document.getElementById('noResults').classList.add('hidden');
            document.getElementById('errorState').classList.add('hidden');
            document.getElementById('welcomeState').classList.add('hidden');
            document.getElementById('loadingState').classList.remove('hidden');

            // Update loading message
            const loadingText = document.querySelector('#loadingState p');
            if (loadingText) {
                loadingText.textContent = message;
            }

            const header = document.getElementById('resultsHeader');
            if (header) {
                header.querySelector('h2').innerHTML = '';
                header.querySelector('p').innerHTML = '';
            }

            const pagination = document.getElementById('pagination');
            if (pagination) {
                pagination.innerHTML = '';
            }

            lastSearchParams = null;

            const button = document.querySelector('button[type="submit"]');
            const searchText = button.querySelector('.search-text');
            const loading = button.querySelector('.loading');

            searchText.classList.add('hidden');
            loading.classList.remove('hidden');
            loading.style.display = 'inline-block';
            button.disabled = true;
        }

        function hideLoadingState() {
            document.getElementById('loadingState').classList.add('hidden');

            const button = document.querySelector('button[type="submit"]');
            const searchText = button.querySelector('.search-text');
            const loading = button.querySelector('.loading');

            searchText.classList.remove('hidden');
            loading.classList.add('hidden');
            loading.style.display = 'none';
            button.disabled = false;
        }

        function displayResults(data, skipRendering = false) {
            // Hide other states
            document.getElementById('loadingState').classList.add('hidden');
            document.getElementById('noResults').classList.add('hidden');
            document.getElementById('errorState').classList.add('hidden');
            document.getElementById('welcomeState').classList.add('hidden');

            const shownCount = Array.isArray(data.results) ? data.results.length : 0;
            const resultsList = document.getElementById('resultsList');
            updateResultsHeader(data, shownCount);

            const currentQuery = data.query ?? document.getElementById('searchInput').value.trim();
            const currentEpisodeType = data.episode_type ?? document.querySelector('select[name="episode_type"]').value ?? 'all';
            const currentSort = data.sort ?? document.querySelector('select[name="sort"]').value ?? 'relevance';
            const currentPage = Math.max(parseInt(data.page ?? 1, 10) || 1, 1);
            const currentLimit = parseInt(data.limit ?? shownCount, 10) || (shownCount || 10);
            const totalResults = typeof data.total === 'number' ? data.total : shownCount;

            lastSearchParams = {
                query: currentQuery,
                cleanQuery: data.clean_query ?? currentQuery,
                episodeType: currentEpisodeType,
                sort: currentSort,
                page: currentPage,
                limit: currentLimit,
                total: totalResults
            };

            if (skipRendering) {
                document.getElementById('results').classList.remove('hidden');
                return;
            }

            // Generate pagination for the current result set
            generatePagination({
                ...data,
                query: currentQuery,
                episode_type: currentEpisodeType,
                sort: currentSort,
                page: currentPage,
                limit: currentLimit,
                total: totalResults
            });

            if (resultsList) {
                resultsList.innerHTML = '';
            }

            if (shownCount === 0) {
                document.getElementById('noResults').classList.remove('hidden');
                return;
            }

            if (resultsList) {
                resultsList.innerHTML = data.results.map(result => `
                <div class="result-card clickable-result bg-white rounded-lg border border-gray-200 p-6 relative hover:shadow-md transition-shadow"
                     onclick="openEpisodeSidebar(${result.episode_id}, ${result.id})"
                     data-episode-id="${result.episode_id}"
                     data-segment-id="${result.id}">

                    <!-- Main Quote (Primary Focus) -->
                    <div class="mb-4">
                        <div class="text-gray-900 text-lg leading-relaxed font-medium">
                            ${result.highlighted_text}
                        </div>
                    </div>

                    <!-- Date & Timestamp (Secondary) -->
                    <div class="mb-3 text-sm text-gray-600">
                        <span>
                            ${result.formatted_date}
                        </span>
                        <span class="mx-2">•</span>
                        <span>
                            ${result.formatted_time} - ${formatTime(result.end_time)}
                        </span>
                    </div>

                    <!-- Episode Title & Type (Tertiary) -->
                    <div class="text-sm text-gray-600">
                        <span class="italic font-medium">
                            ${result.episode_title}
                        </span>
                        <span class="mx-2">•</span>
                        <span class="text-gray-500 italic">
                            ${result.episode_type === 'midweek_mayhem' ? 'Midweek Mayhem' : 'Interview'}
                        </span>
                    </div>
                </div>
            `).join('');
            }

            document.getElementById('results').classList.remove('hidden');
        }

        function updateResultsHeader(data, shownCount = 0) {
            const header = document.getElementById('resultsHeader');
            if (!header) {
                return;
            }

            const titleEl = header.querySelector('h2');
            const metaEl = header.querySelector('p');
            if (!titleEl || !metaEl) {
                return;
            }

            const total = typeof data.total === 'number' ? data.total : shownCount;
            const page = Math.max(parseInt(data.page ?? 1, 10) || 1, 1);
            let limit = parseInt(data.limit ?? shownCount, 10);
            if (!Number.isFinite(limit) || limit <= 0) {
                if (shownCount > 0) {
                    limit = shownCount;
                } else if (total > 0) {
                    limit = total;
                } else {
                    limit = 1;
                }
            }

            const remaining = Math.max(total - (page - 1) * limit, 0);
            const effectiveShown = shownCount > 0 ? shownCount : Math.min(limit, remaining);

            if (total <= 0) {
                titleEl.textContent = 'Found 0 results';
            } else if (total <= limit && page === 1) {
                titleEl.textContent = `Found ${total.toLocaleString()} results`;
            } else if (effectiveShown <= 0) {
                titleEl.textContent = `Found ${total.toLocaleString()} results`;
            } else {
                const first = ((page - 1) * limit) + 1;
                const last = first + effectiveShown - 1;
                titleEl.textContent = `Showing ${first.toLocaleString()} - ${last.toLocaleString()} of ${total.toLocaleString()} results`;
            }

            const sortLabels = {
                relevance: 'Sorted by relevance',
                newest: 'Newest first',
                oldest: 'Oldest first'
            };
            const typeLabels = {
                all: 'All episode types',
                interview: 'Interviews only',
                midweek_mayhem: 'Midweek Mayhem only'
            };

            const sortKey = (data.sort ?? 'relevance').toString().toLowerCase();
            const episodeTypeKey = (data.episode_type ?? 'all').toString().toLowerCase();

            const summaryParts = [];
            if (typeLabels[episodeTypeKey]) {
                summaryParts.push(typeLabels[episodeTypeKey]);
            }
            if (sortLabels[sortKey]) {
                summaryParts.push(sortLabels[sortKey]);
            }

            metaEl.textContent = summaryParts.join(' • ');
        }

        function generatePagination(data) {
            const paginationContainer = document.getElementById('pagination');
            if (!paginationContainer) {
                return;
            }

            const total = typeof data.total === 'number' ? data.total : 0;
            let limit = parseInt(data.limit, 10);
            if (!Number.isFinite(limit) || limit <= 0) {
                if (lastSearchParams?.limit) {
                    limit = lastSearchParams.limit;
                } else if (total > 0) {
                    limit = total;
                } else {
                    paginationContainer.innerHTML = '';
                    return;
                }
            }

            const totalPages = limit > 0 ? Math.max(Math.ceil(total / limit), 1) : 1;
            const currentPage = Math.max(parseInt(data.page, 10) || 1, 1);

            if (totalPages <= 1) {
                paginationContainer.innerHTML = '';
                return;
            }

            const query = data.query ?? '';
            const episodeType = data.episode_type ?? 'all';
            const sort = data.sort ?? 'relevance';

            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);

            let paginationHTML = '<nav class="flex items-center space-x-2">';

            if (currentPage > 1) {
                const prevPage = currentPage - 1;
                const prevUrl = buildSearchUrl(query, prevPage, episodeType, sort);
                paginationHTML += `
                    <a href="${prevUrl}" data-page="${prevPage}" class="pagination-button">
                        Previous
                    </a>
                `;
            } else {
                paginationHTML += `
                    <span class="pagination-button pagination-button-disabled">
                        Previous
                    </span>
                `;
            }

            if (startPage > 1) {
                const firstUrl = buildSearchUrl(query, 1, episodeType, sort);
                paginationHTML += `
                    <a href="${firstUrl}" data-page="1" class="pagination-button">1</a>
                `;
                if (startPage > 2) {
                    paginationHTML += '<span class="pagination-ellipsis">…</span>';
                }
            }

            for (let page = startPage; page <= endPage; page++) {
                if (page === currentPage) {
                    paginationHTML += `
                        <span class="pagination-button pagination-button-active">
                            ${page}
                        </span>
                    `;
                } else {
                    const pageUrl = buildSearchUrl(query, page, episodeType, sort);
                    paginationHTML += `
                        <a href="${pageUrl}" data-page="${page}" class="pagination-button">
                            ${page}
                        </a>
                    `;
                }
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    paginationHTML += '<span class="pagination-ellipsis">…</span>';
                }
                const lastUrl = buildSearchUrl(query, totalPages, episodeType, sort);
                paginationHTML += `
                    <a href="${lastUrl}" data-page="${totalPages}" class="pagination-button">
                        ${totalPages}
                    </a>
                `;
            }

            if (currentPage < totalPages) {
                const nextPage = currentPage + 1;
                const nextUrl = buildSearchUrl(query, nextPage, episodeType, sort);
                paginationHTML += `
                    <a href="${nextUrl}" data-page="${nextPage}" class="pagination-button">
                        Next
                    </a>
                `;
            } else {
                paginationHTML += `
                    <span class="pagination-button pagination-button-disabled">
                        Next
                    </span>
                `;
            }

            paginationHTML += '</nav>';
            paginationContainer.innerHTML = paginationHTML;
        }

        function buildSearchUrl(query, page = 1, episodeType = 'all', sort = 'relevance') {
            const params = new URLSearchParams();
            const trimmedQuery = (query ?? '').toString().trim();
            if (trimmedQuery.length) {
                params.set('q', trimmedQuery);
            }
            if (episodeType && episodeType !== 'all') {
                params.set('episode_type', episodeType);
            }
            if (sort && sort !== 'relevance') {
                params.set('sort', sort);
            }
            if (page > 1) {
                params.set('page', page);
            }
            const queryString = params.toString();
            return queryString ? `/?${queryString}` : '/';
        }

        function handlePaginationClick(event) {
            if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
                return;
            }

            const link = event.target.closest('a[data-page]');
            if (!link) {
                return;
            }

            event.preventDefault();

            const targetPage = parseInt(link.dataset.page, 10);
            if (!Number.isFinite(targetPage) || targetPage < 1) {
                return;
            }

            if (!lastSearchParams || typeof lastSearchParams.query !== 'string' || !lastSearchParams.query.length) {
                window.location.href = link.getAttribute('href');
                return;
            }

            performSearch(
                lastSearchParams.query,
                lastSearchParams.episodeType,
                lastSearchParams.sort,
                targetPage,
                { updateHistory: true }
            );
        }
        function showError(message) {
            // Hide other states
            document.getElementById('loadingState').classList.add('hidden');
            document.getElementById('results').classList.add('hidden');
            document.getElementById('noResults').classList.add('hidden');
            document.getElementById('welcomeState').classList.add('hidden');

            // Show error
            document.getElementById('errorMessage').textContent = message;
            document.getElementById('errorState').classList.remove('hidden');
        }

        // Allow Enter key to submit
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('searchForm').dispatchEvent(new Event('submit'));
            }
        });

        // Auto-search when filter dropdowns change
        document.querySelector('select[name="episode_type"]').addEventListener('change', async function() {
            // Update hidden form field
            document.getElementById('formEpisodeType').value = this.value;
            await triggerAutoSearch();
        });

        document.querySelector('select[name="sort"]').addEventListener('change', async function() {
            // Update hidden form field
            document.getElementById('formSort').value = this.value;
            await triggerAutoSearch();
        });

        // Auto-search episodes when input is cleared and episode filter is active
        document.getElementById('searchInput').addEventListener('input', async function(e) {
            const value = e.target.value.trim();
            const episodesToggle = document.getElementById('episodes');
            const currentToggleView = episodesToggle.checked ? 'episodes' : 'transcripts';
            if (value === '') {
                // Remove 'q' param from URL if present
                const url = new URL(window.location);
                url.searchParams.delete('q');
                window.history.pushState({}, '', url);
                updatePageTitle('');
                if (currentToggleView === 'episodes') {
                    await loadEpisodes(1);
                } else {
                    // For transcripts view, clear results but keep filters
                    clearResultsButKeepFilters();
                }
            }
        });

        // Function to trigger search when filters change
        async function triggerAutoSearch() {
            const query = document.getElementById('searchInput').value.trim();

            // Get current toggle state
            const episodesToggle = document.getElementById('episodes');
            const currentToggleView = episodesToggle.checked ? 'episodes' : 'transcripts';

            // Get current filter values
            const episodeType = document.querySelector('select[name="episode_type"]').value;
            const sort = document.querySelector('select[name="sort"]').value;

            // Update URL with current filter values
            const url = new URL(window.location);

            if (episodeType !== 'all') {
                url.searchParams.set('episode_type', episodeType);
            } else {
                url.searchParams.delete('episode_type');
            }

            if (currentToggleView === 'episodes') {
                // For episodes view, use 'newest' as default sort
                if (sort !== 'newest') {
                    url.searchParams.set('sort', sort);
                } else {
                    url.searchParams.delete('sort');
                }

                // Update URL and reload episodes with new filters
                url.searchParams.delete('page');
                window.history.pushState({}, '', url);
                updatePageTitle(query);
                await loadEpisodes(1, { updateHistory: false });
            } else {
                // For transcripts view, use 'relevance' as default sort
                if (sort !== 'relevance') {
                    url.searchParams.set('sort', sort);
                } else {
                    url.searchParams.delete('sort');
                }

                if (query) {
                    // Update URL and re-search transcripts with new filters
                    url.searchParams.delete('page');
                    window.history.pushState({}, '', url);
                    updatePageTitle(query);
                    await performSearch(query, episodeType, sort, 1, { updateHistory: false });
                } else {
                    // No query but update URL with new filter values
                    url.searchParams.delete('page');
                    window.history.pushState({}, '', url);
                    updatePageTitle('');
                }
            }
        }

        // Handle browser back/forward buttons
        window.addEventListener('popstate', function(event) {
            // Close sidebar if it's open when navigating back/forward
            const sidebar = document.getElementById('episodeSidebar');
            if (sidebar && sidebar.classList.contains('open')) {
                closeSidebar();
            }

            // Force search when navigating back/forward to ensure results match URL
            handleUrlParameters(true);
        });

        // ===== EPISODE LISTING FUNCTIONS =====

        // Load episodes from server
        async function loadEpisodes(page = 1, options = { updateHistory: true }) {
            const episodeType = document.querySelector('select[name="episode_type"]').value || 'all';
            const sort = document.querySelector('select[name="sort"]').value || 'newest';
            const searchQuery = document.getElementById('searchInput').value.trim();

            // Update URL with page parameter
            if (options.updateHistory) {
                if (page > 1) {
                    const url = new URL(window.location);
                    url.searchParams.set('page', page);
                    window.history.pushState({}, '', url);
                } else {
                    // Remove page parameter for page 1
                    const url = new URL(window.location);
                    url.searchParams.delete('page');
                    window.history.pushState({}, '', url);
                }
            }

            // Check if we can use server-side episode data
            if (serverEpisodeData) {
                const serverPage = (serverEpisodeData.pagination && serverEpisodeData.pagination.current_page) || 1;

                // Only use server data if it matches the requested page
                if (page === serverPage) {
                    const serverEpisodeType = (serverEpisodeData.filters && serverEpisodeData.filters.episode_type) || 'all';
                    const serverSort = (serverEpisodeData.filters && serverEpisodeData.filters.sort) || 'newest';
                    const serverQuery = (serverEpisodeData.filters && serverEpisodeData.filters.q) || '';

                    // If filters match server-side data, use it instead of making a request
                    if (episodeType === serverEpisodeType && sort === serverSort && searchQuery === serverQuery) {
                        displayEpisodeCards(serverEpisodeData);
                        return;
                    }
                }
            }

            // Show loading state and hide all results
            showLoadingState('Loading episodes...');
            hideAllResults();

            try {
                // Build AJAX request parameters
                const params = new URLSearchParams({
                    view: 'episodes',
                    episode_type: episodeType,
                    sort: sort,
                    page: page,
                    ajax: '1' // Request JSON response
                });

                // Add search query if provided
                if (searchQuery) {
                    params.set('q', searchQuery);
                }

                const response = await fetch(`/?${params.toString()}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();

                if (data.success) {
                    displayEpisodeCards(data);
                } else {
                    showError(data.error || 'Failed to load episodes');
                }
            } catch (error) {
                console.error('Episode loading error:', error);
                showError('Failed to load episodes');
            } finally {
                hideLoadingState();
            }
        }

        // Display episode cards
        function displayEpisodeCards(data) {
            hideAllResults();

            const container = document.getElementById('episodeCards');
            const cardsList = document.getElementById('episodeCardsList');
            const pagination = document.getElementById('episodePagination');

            // Clear previous content
            cardsList.innerHTML = '';
            pagination.innerHTML = '';

            // Show no results panel if no episodes
            if (!data.episodes || data.episodes.length === 0) {
                document.getElementById('noResults').classList.remove('hidden');
                return;
            }

            // Create episode cards
            data.episodes.forEach(episode => {
                const card = createEpisodeCard(episode);
                cardsList.appendChild(card);
            });

            // Create pagination
            if (data.pagination && data.pagination.last_page > 1) {
                createEpisodePagination(data.pagination, pagination);
            }

            // Show the episodes container
            container.classList.remove('hidden');
        }

        // Create a single episode card
        function createEpisodeCard(episode) {
            const card = document.createElement('a');
            card.href = `/?view=episodes&episode=${episode.id}`;
            card.className = 'block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow cursor-pointer text-left';
            card.onclick = (e) => {
                e.preventDefault();
                openEpisodeFromCard(episode);
            };

            // Determine status badge
            let statusBadge = '';
            if (episode.transcription_status === 'completed') {
                statusBadge = '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Transcribed</span>';
            } else if (episode.transcription_status === 'processing') {
                statusBadge = '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Processing</span>';
            } else {
                statusBadge = '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Not Transcribed</span>';
            }

            card.innerHTML = `
                <div class="mb-3">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2 line-clamp-2">${episode.title}</h3>
                    <div class="flex items-center text-sm text-gray-500 mb-2">
                        <span>${episode.formatted_date}</span>
                        ${episode.episode_type === 'midweek_mayhem' ? `<span class="mx-2">•</span><span>Midweek Mayhem</span>` : ''}
                    </div>
                </div>
                ${episode.short_description ? `
                    <p class="text-gray-600 text-sm line-clamp-3 mb-4">${episode.short_description}</p>
                ` : ''}
                <div class="flex items-center justify-between">
                    <span class="text-sm text-blue-600 font-medium">Click to view transcript</span>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>
            `;

            return card;
        }

        // Create pagination for episodes
        function createEpisodePagination(pagination, container) {
            const nav = document.createElement('nav');
            nav.className = 'flex items-center justify-between';

            // Page buttons
            const buttons = document.createElement('div');
            buttons.className = 'flex space-x-2';

            // Previous button
            if (pagination.current_page > 1) {
                const prevBtn = document.createElement('a');
                prevBtn.href = buildEpisodeUrl(pagination.current_page - 1);
                prevBtn.className = 'pagination-button';
                prevBtn.textContent = 'Previous';
                prevBtn.onclick = async (e) => {
                    e.preventDefault();
                    await loadEpisodes(pagination.current_page - 1);
                };
                buttons.appendChild(prevBtn);
            } else {
                const prevBtn = document.createElement('span');
                prevBtn.className = 'pagination-button pagination-button-disabled';
                prevBtn.textContent = 'Previous';
                buttons.appendChild(prevBtn);
            }

            // Page numbers (show current and a few around it)
            const startPage = Math.max(1, pagination.current_page - 2);
            const endPage = Math.min(pagination.last_page, pagination.current_page + 2);

            if (startPage > 1) {
                const firstBtn = document.createElement('a');
                firstBtn.href = buildEpisodeUrl(1);
                firstBtn.className = 'pagination-button';
                firstBtn.textContent = '1';
                firstBtn.onclick = async (e) => {
                    e.preventDefault();
                    await loadEpisodes(1);
                };
                buttons.appendChild(firstBtn);

                if (startPage > 2) {
                    const ellipsis = document.createElement('span');
                    ellipsis.className = 'pagination-ellipsis';
                    ellipsis.textContent = '…';
                    buttons.appendChild(ellipsis);
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                if (i === pagination.current_page) {
                    const pageBtn = document.createElement('span');
                    pageBtn.className = 'pagination-button pagination-button-active';
                    pageBtn.textContent = i;
                    buttons.appendChild(pageBtn);
                } else {
                    const pageBtn = document.createElement('a');
                    pageBtn.href = buildEpisodeUrl(i);
                    pageBtn.className = 'pagination-button';
                    pageBtn.textContent = i;
                    pageBtn.onclick = async (e) => {
                        e.preventDefault();
                        await loadEpisodes(i);
                    };
                    buttons.appendChild(pageBtn);
                }
            }

            if (endPage < pagination.last_page) {
                if (endPage < pagination.last_page - 1) {
                    const ellipsis = document.createElement('span');
                    ellipsis.className = 'pagination-ellipsis';
                    ellipsis.textContent = '…';
                    buttons.appendChild(ellipsis);
                }

                const lastBtn = document.createElement('a');
                lastBtn.href = buildEpisodeUrl(pagination.last_page);
                lastBtn.className = 'pagination-button';
                lastBtn.textContent = pagination.last_page;
                lastBtn.onclick = async (e) => {
                    e.preventDefault();
                    await loadEpisodes(pagination.last_page);
                };
                buttons.appendChild(lastBtn);
            }

            // Next button
            if (pagination.current_page < pagination.last_page) {
                const nextBtn = document.createElement('a');
                nextBtn.href = buildEpisodeUrl(pagination.current_page + 1);
                nextBtn.className = 'pagination-button';
                nextBtn.textContent = 'Next';
                nextBtn.onclick = async (e) => {
                    e.preventDefault();
                    await loadEpisodes(pagination.current_page + 1);
                };
                buttons.appendChild(nextBtn);
            } else {
                const nextBtn = document.createElement('span');
                nextBtn.className = 'pagination-button pagination-button-disabled';
                nextBtn.textContent = 'Next';
                buttons.appendChild(nextBtn);
            }

            nav.appendChild(buttons);
            container.appendChild(nav);
        }

        function buildEpisodeUrl(page) {
            const episodeType = document.querySelector('select[name="episode_type"]').value;
            const sort = document.querySelector('select[name="sort"]').value;
            const searchQuery = document.getElementById('searchInput').value.trim();

            const params = new URLSearchParams();
            params.set('view', 'episodes');
            if (page > 1) params.set('page', page);
            if (episodeType !== 'all') params.set('episode_type', episodeType);
            if (sort !== 'newest') params.set('sort', sort);
            if (searchQuery) params.set('q', searchQuery);

            return '/?' + params.toString();
        }

        // Open episode sidebar from episode card (reuse existing sidebar functionality)
        async function openEpisodeFromCard(episode) {
            try {
                // First, fetch the episode segments to get the first segment ID
                const response = await fetch(`/episode/${episode.id}/segments`);
                const data = await response.json();

                if (data.success && data.segments && data.segments.length > 0) {
                    // Get the first segment ID
                    const firstSegmentId = data.segments[0].id;

                    // Use the existing openEpisodeSidebar function exactly like search results do
                    // This will load the episode, open the sidebar, and start playing from the first segment
                    openEpisodeSidebar(episode.id, firstSegmentId);
                } else {
                    // If no segments available, still open the sidebar but without a specific segment
                    openEpisodeSidebar(episode.id, null);
                }
            } catch (error) {
                console.error('Failed to load episode segments:', error);
                // Fallback to opening without specific segment
                openEpisodeSidebar(episode.id, null);
            }
        }

        // Hide all result containers
        function hideAllResults() {
            document.getElementById('results').classList.add('hidden');
            document.getElementById('episodeCards').classList.add('hidden');
            document.getElementById('welcomeState').classList.add('hidden');
            document.getElementById('loadingState').classList.add('hidden');
            document.getElementById('noResults').classList.add('hidden');
            document.getElementById('errorState').classList.add('hidden');
            document.getElementById('welcomeState').classList.add('hidden');
        }

        // ===== END EPISODE LISTING FUNCTIONS =====

        // Info Modal functionality
        window.openInfoModal = function() {
            const modal = document.getElementById('infoModal');
            const backdrop = document.getElementById('infoModalBackdrop');

            if (!modal || !backdrop) {
                console.error('Modal elements not found');
                return;
            }

            backdrop.classList.add('open');
            modal.classList.add('open');
        }

        window.closeInfoModal = function() {
            const modal = document.getElementById('infoModal');
            const backdrop = document.getElementById('infoModalBackdrop');

            if (!modal || !backdrop) {
                console.error('Modal elements not found');
                return;
            }

            backdrop.classList.remove('open');
            modal.classList.remove('open');
        }

        // Close info modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modal = document.getElementById('infoModal');
                const episodeModal = document.getElementById('episodeInfoModal');
                if (modal && modal.classList.contains('open')) {
                    closeInfoModal();
                }
                if (episodeModal && episodeModal.classList.contains('open')) {
                    closeEpisodeInfoModal();
                }
            }
        });

        // Episode Info Modal Functions
        window.openEpisodeInfoModal = function() {
            const modal = document.getElementById('episodeInfoModal');
            const backdrop = document.getElementById('episodeInfoModalBackdrop');

            if (!modal || !backdrop) {
                console.error('Episode modal elements not found');
                return;
            }

            backdrop.classList.add('open');
            modal.classList.add('open');
        }

        window.closeEpisodeInfoModal = function() {
            const modal = document.getElementById('episodeInfoModal');
            const backdrop = document.getElementById('episodeInfoModalBackdrop');

            if (!modal || !backdrop) {
                console.error('Episode modal elements not found');
                return;
            }

            backdrop.classList.remove('open');
            modal.classList.remove('open');
        }

        // Open episode sidebar
        async function openEpisodeSidebar(episodeId, segmentId) {
            // Reset state variables whenever the sidebar is opened
            activeSegmentId = null;
            manualSegmentOverride = false;

            const sidebar = document.getElementById('episodeSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            const transcriptSegments = document.getElementById('transcriptSegments');

            // Check if sidebar is already populated with server-rendered content for THIS episode
            const currentEpisodeId = sidebar.getAttribute('data-current-episode-id');
            const isPrePopulated = transcriptSegments &&
                                   transcriptSegments.children.length > 0 &&
                                   currentEpisodeId == episodeId; // Matches the requested episode

            if (isPrePopulated) {
                // Sidebar already has content from server for this exact episode - just open it
                sidebar.classList.add('open');
                backdrop.classList.add('open');

                // Update URL with episode and segment id
                const url = new URL(window.location);
                url.searchParams.set('episode', episodeId);
                if (segmentId) {
                    url.searchParams.set('segment', segmentId);
                } else {
                    url.searchParams.delete('segment');
                }
                window.history.replaceState({}, '', url);

                // Store episode info from pre-rendered data for modal use
                const titleEl = document.getElementById('sidebarEpisodeTitle');
                const descriptionEl = document.getElementById('episodeInfoModalContent');
                if (titleEl && descriptionEl) {
                    window.currentEpisodeInfo = {
                        id: episodeId,
                        title: titleEl.textContent.trim(),
                        description: descriptionEl.innerHTML || ''
                    };
                }

                // Build currentEpisodeData from pre-rendered DOM for autoplay functionality
                const segments = Array.from(transcriptSegments.children).map(segmentEl => ({
                    id: parseInt(segmentEl.getAttribute('data-segment-id')),
                    start_time: parseFloat(segmentEl.getAttribute('data-start-time'))
                }));

                currentEpisodeData = {
                    episode: window.currentEpisodeInfo,
                    segments: segments
                };

                // Hide loading state
                showSidebarLoading(false);

                // Determine target segment (default to first if not provided)
                let segId = segmentId;
                if (!segId && segments.length > 0) {
                    segId = segments[0].id;
                }

                // Handle segment highlighting and scrolling
                if (segId) {
                    setTimeout(() => {
                        setActiveSegment(segId);
                        scrollToSegment(segId);
                    }, 400);
                }

                // Store target segment for auto-seek when audio loads
                targetSegmentForAutoSeek = segId;

                // Initialize audio player
                loadAudio(episodeId);

                return;
            }

            // Need to fetch new episode data (either not pre-populated or different episode)
            showSidebarLoading(true);
            sidebar.classList.add('open');
            backdrop.classList.add('open');

            try {
                // Fetch episode data
                const response = await fetch(`/episode/${episodeId}/segments`);
                const data = await response.json();

                if (data.success) {
                    currentEpisodeData = data;
                    let segId = segmentId;
                    // If no segmentId provided, use the first segment's id if available
                    if (!segId && data.segments && data.segments.length > 0) {
                        segId = data.segments[0].id;
                    }
                    populateSidebar(data, segId);

                    // Update the data attribute to track the current episode
                    sidebar.setAttribute('data-current-episode-id', episodeId);

                    // Store target segment for auto-seek when audio loads
                    targetSegmentForAutoSeek = segId;
                    loadAudio(episodeId);
                } else {
                    showSidebarError('Failed to load episode data');
                }
            } catch (error) {
                showSidebarError('Network error occurred');
            }
        }

        // Close sidebar
        function closeSidebar() {
            const sidebar = document.getElementById('episodeSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');

            sidebar.classList.remove('open');
            backdrop.classList.remove('open');

            // Remove episode and segment params from URL
            (function removeSidebarParams() {
                const url = new URL(window.location);
                url.searchParams.delete('episode');
                url.searchParams.delete('segment');
                window.history.replaceState({}, '', url);
            })();

            // Clean up audio
            if (currentAudio) {
                currentAudio.pause();
                // Remove the timeupdate event listener
                if (audioTimeUpdateHandler) {
                    currentAudio.removeEventListener('timeupdate', audioTimeUpdateHandler);
                    audioTimeUpdateHandler = null;
                }
                currentAudio = null;
            }

            // Clear episode data
            currentEpisodeData = null;
            activeSegmentId = null;
            targetSegmentForAutoSeek = null;
            manualSegmentOverride = false;
        }

        // Show/hide sidebar loading state
        function showSidebarLoading(show) {
            const loading = document.getElementById('sidebarLoading');
            const content = document.querySelector('#episodeSidebar > div:not(#sidebarLoading)');

            if (show) {
                loading.classList.remove('hidden');
                if (content) content.style.display = 'none';
            } else {
                loading.classList.add('hidden');
                if (content) content.style.display = 'block';
            }
        }

        // Show sidebar error
        function showSidebarError(message) {
            const loading = document.getElementById('sidebarLoading');
            loading.innerHTML = `
                <div class="text-center">
                    <div class="text-red-500 text-6xl mb-4">⚠️</div>
                    <p class="text-red-600">${message}</p>
                    <button onclick="closeSidebar()" class="mt-4 px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">Close</button>
                </div>
            `;
        }

        // Populate sidebar with episode data
        function populateSidebar(data, targetSegmentId) {
            showSidebarLoading(false);

            const episode = data.episode;

            // Store episode data globally for modal use
            window.currentEpisodeInfo = episode;

            // --- Update URL with episode and segment id ---
            (function updateSidebarUrl() {
                const url = new URL(window.location);
                url.searchParams.set('episode', episode.id);
                if (targetSegmentId) {
                    url.searchParams.set('segment', targetSegmentId);
                } else {
                    url.searchParams.delete('segment');
                }
                window.history.replaceState({}, '', url);
            })();

            // Update episode info
            document.getElementById('sidebarEpisodeTitle').innerHTML = episode.title;

            // Update episode meta (date • type)
            const episodeMetaEl = document.getElementById('sidebarEpisodeMeta');
            const episodeType = episode.episode_type === 'midweek_mayhem' ? 'Midweek Mayhem' : 'Interview';
            episodeMetaEl.textContent = `${episode.formatted_date} • ${episodeType}`;

            // Populate episode info modal content
            document.getElementById('episodeInfoModalTitle').textContent = episode.title;
            // Turn every two new lines into 1 <br>
            document.getElementById('episodeInfoModalContent').innerHTML = (episode.description || 'No description available.').replace(/\n{2,}/g, '<br>');

            // Populate transcript segments
            const segmentsContainer = document.getElementById('transcriptSegments');
            segmentsContainer.innerHTML = data.segments.map(segment => `
                <div class="segment-item p-4 border-b border-gray-100"
                     data-segment-id="${segment.id}"
                     data-start-time="${segment.start_time}"
                     onclick="jumpToSegment(${segment.id}, ${segment.start_time})">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-xs text-blue-600 font-mono">${segment.formatted_time} - ${formatTime(segment.end_time)}</span>
                    </div>
                    <div class="text-sm text-gray-700 leading-relaxed">${segment.text}</div>
                </div>
            `).join('');

            // Set active segment and scroll to it after DOM is ready
            if (targetSegmentId) {
                setTimeout(() => {
                    setActiveSegment(targetSegmentId);
                    scrollToSegment(targetSegmentId);
                }, 400); // Wait for sidebar transition and DOM update
            }
        }

        // Load and setup audio player
        function loadAudio(episodeId) {
            const audio = document.getElementById('episodeAudio');
            const audioSource = document.getElementById('audioSource');

            // Set audio source
            audioSource.src = `/episode/${episodeId}/audio`;
            audio.load();

            // Reinitialize Plyr for the sidebar if it exists
            if (window.sidebarPlayer) {
                window.sidebarPlayer.destroy();
                window.sidebarPlayer = null;
            }

            // Wait a moment for the audio to load, then reinitialize Plyr
            setTimeout(() => {
                if (audio && !window.sidebarPlayer && window.Plyr) {
                    window.sidebarPlayer = new window.Plyr(audio, {
                        controls: [
                            'play', // Play/pause playback
                            'progress', // The progress bar and scrubber for playback and buffering
                            'current-time', // The current time of playback
                            'duration', // The full duration of the media
                            'mute', // Toggle mute
                            'volume', // Volume control
                            'settings' // Settings menu
                        ],
                        seekTime: 15, // 15 seconds skip
                        displayDuration: true,
                        storage: { enabled: true, key: 'plyr_sidebar' },
                        speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
                        keyboard: { focused: false, global: false }, // Disable global shortcuts for sidebar
                        tooltips: { controls: true, seek: true }
                    });

                    setupSidebarPlayerEvents();
                } else if (audio && !window.Plyr) {
                    setupFallbackAudioEvents(audio);
                }
            }, 200);
        }

        // Setup fallback HTML5 audio events
        function setupFallbackAudioEvents(audio) {

            audio.addEventListener('loadstart', () => {
                // Audio loading started
            });

            audio.addEventListener('canplay', () => {
                currentAudio = audio;
                setupAudioTimeTracking();
                handleAutoSeekAndPlay(audio);
            });

            audio.addEventListener('error', () => {
                // Audio failed to load
            });

            audio.addEventListener('loadedmetadata', () => {
                // Audio metadata loaded
            });
        }

        // Setup sidebar player events
        function setupSidebarPlayerEvents() {
            const player = window.sidebarPlayer;

            if (!player) return;

            // Plyr event listeners
            player.on('loadstart', () => {
                // Audio loading started
            });

            player.on('canplay', () => {
                currentAudio = player;
                setupAudioTimeTracking();
                handleAutoSeekAndPlay(player);
            });

            player.on('error', () => {
                // Audio failed to load
            });

            player.on('loadedmetadata', () => {
                // Audio metadata loaded
            });

            player.on('ready', () => {
                handleAutoSeekAndPlay(player);
            });
        }

        // Handle auto-seek and auto-play functionality
        function handleAutoSeekAndPlay(player) {
            // Auto-seek and auto-play if we have a target segment
            if (targetSegmentForAutoSeek && currentEpisodeData) {
                const targetSegment = currentEpisodeData.segments.find(seg => seg.id == targetSegmentForAutoSeek);

                if (targetSegment) {
                    // Wait a moment to ensure the player is fully ready
                    setTimeout(() => {
                        player.currentTime = targetSegment.start_time;

                        // Attempt auto-play
                        const playPromise = player.play ? player.play() : Promise.resolve();
                        playPromise.then(() => {
                        }).catch(e => {
                            console.log('Auto-play prevented by browser policy:', e.message);
                        });
                    }, 500);
                }
                targetSegmentForAutoSeek = null; // Reset after use
            }
        }

        // Setup audio time tracking for automatic segment highlighting
        function setupAudioTimeTracking() {
            if (!currentAudio || !currentEpisodeData) return;

            const isPlyr = !!window.sidebarPlayer;

            // Remove existing listener if it exists
            if (audioTimeUpdateHandler) {
                if (isPlyr) {
                    currentAudio.off('timeupdate', audioTimeUpdateHandler);
                } else {
                    currentAudio.removeEventListener('timeupdate', audioTimeUpdateHandler);
                }
            }

            // Create new handler function
            audioTimeUpdateHandler = () => {
                if (!currentAudio || !currentEpisodeData || manualSegmentOverride) return;

                const currentTime = currentAudio.currentTime;
                const currentSegment = findSegmentByTime(currentTime);

                if (currentSegment && currentSegment.id !== activeSegmentId) {
                    setActiveSegment(currentSegment.id);
                }
            };

            // Add the event listener
            if (isPlyr) {
                currentAudio.on('timeupdate', audioTimeUpdateHandler);
            } else {
                currentAudio.addEventListener('timeupdate', audioTimeUpdateHandler);
            }
        }

        // Find segment by audio time
        function findSegmentByTime(currentTime) {
            if (!currentEpisodeData) return null;
            const segments = currentEpisodeData.segments;
            for (let i = 0; i < segments.length; i++) {
                const seg = segments[i];
                const nextSeg = segments[i + 1];
                if (nextSeg) {
                    // If not last segment, highlight if currentTime >= start and < next start
                    if (currentTime >= seg.start_time && currentTime < nextSeg.start_time) {
                        return seg;
                    }
                } else {
                    // Last segment: highlight if currentTime >= start (or <= end for legacy)
                    if (currentTime >= seg.start_time) {
                        return seg;
                    }
                }
            }
            return null;
        }

        // Jump to specific segment
        function jumpToSegment(segmentId, startTime) {
            // Set manual override to prevent auto-tracking conflicts
            manualSegmentOverride = true;

            // --- Update URL segment param on user click ---
            (function updateSegmentParam() {
                if (currentEpisodeData && currentEpisodeData.episode) {
                    const url = new URL(window.location);
                    url.searchParams.set('episode', currentEpisodeData.episode.id);
                    url.searchParams.set('segment', segmentId);
                    window.history.replaceState({}, '', url);
                }
            })();

            if (currentAudio) {
                currentAudio.currentTime = startTime;

                // Handle play for both Plyr and HTML5 audio
                let playPromise;
                if (window.sidebarPlayer && currentAudio === window.sidebarPlayer) {
                    // Plyr instance
                    playPromise = currentAudio.play();
                } else if (currentAudio.play) {
                    // HTML5 audio
                    playPromise = currentAudio.play();
                } else {
                    playPromise = Promise.resolve();
                }

                playPromise.then(() => {
                    // Playback started successfully
                }).catch(e => {
                    // Autoplay was prevented
                });
            }

            setActiveSegment(segmentId);

            // Clear manual override after a short delay
            setTimeout(() => {
                manualSegmentOverride = false;
            }, 1000);
        }

        // Set active segment
        function setActiveSegment(segmentId) {
            if (activeSegmentId === segmentId) return; // Already active

            // Remove active class from previous segment
            if (activeSegmentId) {
                const previousSegment = document.querySelector(`.segment-item[data-segment-id="${activeSegmentId}"]`);
                if (previousSegment) {
                    previousSegment.classList.remove('active');
                }
            }

            // Add active class to new segment
            const newSegment = document.querySelector(`.segment-item[data-segment-id="${segmentId}"]`);
            if (newSegment) {
                newSegment.classList.add('active');
                activeSegmentId = segmentId; // Update the active segment ID

                // Scroll the highlighted segment into the center of the panel view
                scrollToSegment(segmentId);
            } else {
                activeSegmentId = null; // Reset if the new segment isn't found
            }
        }

        // Scroll to segment in sidebar
        function scrollToSegment(segmentId) {
            const segment = document.querySelector(`.segment-item[data-segment-id="${segmentId}"]`);
            const segmentsContainer = document.getElementById('transcriptSegments');

            if (segment && segmentsContainer) {
                // Use scrollIntoView with center positioning - this is more reliable
                try {
                    segment.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center',
                        inline: 'nearest'
                    });
                } catch (e) {
                    // Fallback: get element position and scroll manually
                    const containerRect = segmentsContainer.getBoundingClientRect();
                    const segmentRect = segment.getBoundingClientRect();
                    const relativeTop = segmentRect.top - containerRect.top;
                    const scrollTop = segmentsContainer.scrollTop + relativeTop - (containerRect.height / 2) + (segmentRect.height / 2);

                    smoothScrollTo(segmentsContainer, Math.max(0, scrollTop));
                }
            }
        }

        // Manual smooth scroll fallback function
        function smoothScrollTo(element, targetScrollTop, duration = 300) {
            const startScrollTop = element.scrollTop;
            const distance = targetScrollTop - startScrollTop;
            const startTime = performance.now();

            function animation(currentTime) {
                const timeElapsed = currentTime - startTime;
                const progress = Math.min(timeElapsed / duration, 1);

                // Ease-out function for smooth animation
                const ease = 1 - Math.pow(1 - progress, 3);

                element.scrollTop = startScrollTop + (distance * ease);

                if (progress < 1) {
                    requestAnimationFrame(animation);
                }
            }

            requestAnimationFrame(animation);
        }

        // Helper function for time formatting (already exists but making sure it's available)
        // Share sidebar URL to clipboard with toast
        function shareSidebarUrl() {
            const url = window.location.href;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(() => {
                    showShareToast('Link copied to clipboard!');
                }, () => {
                    showShareToast('Failed to copy link');
                });
            } else {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = url;
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    document.execCommand('copy');
                    showShareToast('Link copied to clipboard!');
                } catch (e) {
                    showShareToast('Failed to copy link');
                }
                document.body.removeChild(textarea);
            }
        }

        function showShareToast(message) {
            const toast = document.getElementById('shareToast');
            if (!toast) {
                return;
            }
            toast.textContent = message;
            toast.classList.remove('opacity-0', 'pointer-events-none');
            toast.classList.add('opacity-100');
            setTimeout(() => {
                toast.classList.remove('opacity-100');
                toast.classList.add('opacity-0', 'pointer-events-none');
            }, 1800);
        }
        function formatTime(seconds) {
            if (isNaN(seconds)) return '0:00';

            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const remainingSeconds = Math.floor(seconds % 60);

            if (hours > 0) {
                return `${hours}:${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
            } else {
                return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
            }
        }
    </script>
@endpush
