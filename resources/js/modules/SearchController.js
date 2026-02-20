export class SearchController {
    constructor(uiController, sidebarController) {
        this.ui = uiController;
        this.sidebar = sidebarController;
        this.lastSearchParams = null;
    }

    initialize() {
        this.cacheElements();
        this.initializeButton();
        this.initializeViewToggle();
        this.setupEventListeners();
        this.handleInitialState();
    }

    cacheElements() {
        this.elements = {
            form: document.getElementById('searchForm'),
            searchInput: document.getElementById('searchInput'),
            episodeTypeSelect: document.querySelector('select[name="episode_type"]'),
            sortSelect: document.querySelector('select[name="sort"]'),
            formEpisodeType: document.getElementById('formEpisodeType'),
            formSort: document.getElementById('formSort'),
            episodesRadio: document.getElementById('episodes'),
            transcriptsRadio: document.getElementById('transcripts'),
            pagination: document.getElementById('pagination'),
            viewModeRadios: document.querySelectorAll('input[name="view_mode"]')
        };
    }

    initializeButton() {
        this.ui.setButtonLoading(false);
    }

    setupEventListeners() {
        // Search Form
        if (this.elements.form) {
            this.elements.form.addEventListener('submit', (e) => this.handleSearchSubmit(e));
        }

        // Enter key on input
        if (this.elements.searchInput) {
            this.elements.searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.elements.form.dispatchEvent(new Event('submit'));
                }
            });

            // Auto-search on clear
            this.elements.searchInput.addEventListener('input', (e) => this.handleInput(e));
        }

        // Filters
        if (this.elements.episodeTypeSelect) {
            this.elements.episodeTypeSelect.addEventListener('change', () => {
                if (this.elements.formEpisodeType) this.elements.formEpisodeType.value = this.elements.episodeTypeSelect.value;
                this.triggerAutoSearch();
            });
        }

        if (this.elements.sortSelect) {
            this.elements.sortSelect.addEventListener('change', () => {
                if (this.elements.formSort) this.elements.formSort.value = this.elements.sortSelect.value;
                this.triggerAutoSearch();
            });
        }

        // Pagination delegation
        if (this.elements.pagination) {
            this.elements.pagination.addEventListener('click', (e) => this.handlePaginationClick(e));
        }

        // Browser back/forward: re-sync UI with URL
        window.addEventListener('popstate', () => this.handleUrlParameters());
    }

    initializeViewToggle() {
        if (window.currentView === 'episodes') {
            if (this.elements.episodesRadio) this.elements.episodesRadio.checked = true;
        } else {
            if (this.elements.transcriptsRadio) this.elements.transcriptsRadio.checked = true;
        }

        this.elements.viewModeRadios.forEach(radio => {
            radio.addEventListener('change', async () => {
                if (radio.checked) {
                    await this.handleViewModeChange(radio.value);
                }
            });
        });
    }

    async handleInitialState() {
        // Check for deeplink
        const urlParams = new URLSearchParams(window.location.search);
        const deeplinkEpisodeId = urlParams.get('episode');
        const deeplinkSegmentId = urlParams.get('segment');

        if (deeplinkEpisodeId) {
            await this.sidebar.open(deeplinkEpisodeId, deeplinkSegmentId);
        }

        // Check view mode and server data
        if (window.currentView === 'episodes') {
            if (window.serverEpisodeData) {
                this.handleServerSideEpisodes();
            } else {
                await this.loadEpisodes();
            }
        } else if (window.serverSearchData) {
            this.handleServerSideResults();
        } else {
            this.handleUrlParameters();
        }
    }

    async handleSearchSubmit(e) {
        const formData = new FormData(e.target);
        const query = formData.get('q').trim();
        const episodeType = this.elements.episodeTypeSelect ? this.elements.episodeTypeSelect.value : 'all';
        const sort = this.elements.sortSelect ? this.elements.sortSelect.value : 'relevance';

        if (this.elements.formEpisodeType) this.elements.formEpisodeType.value = episodeType;
        if (this.elements.formSort) this.elements.formSort.value = sort;

        const isEpisodesView = this.elements.episodesRadio && this.elements.episodesRadio.checked;

        if (!query && !isEpisodesView) {
            e.preventDefault();
            if (this.elements.searchInput) this.elements.searchInput.focus();
            return;
        }

        if (isEpisodesView) {
            const url = new URL(window.location);
            url.searchParams.set('view', 'episodes');
            if (query) url.searchParams.set('q', query);
            else url.searchParams.delete('q');

            url.searchParams.set('episode_type', episodeType);
            url.searchParams.set('sort', sort);
            url.searchParams.delete('page');
            window.history.pushState({}, '', url);

            this.ui.updatePageTitle(query);
            await this.loadEpisodes(1, { updateHistory: false });
            e.preventDefault(); // Always prevent default for episodes view AJAX
        } else {
            this.updateUrl(query, episodeType, sort, 1);

            if (window.fetch && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                await this.performSearch(query, episodeType, sort, 1, { updateHistory: false });
            }
        }
    }

    async performSearch(query, episodeType = 'all', sort = 'relevance', page = 1, options = {}) {
        const { updateHistory = false } = options;
        const resolvedPage = Math.max(parseInt(page, 10) || 1, 1);

        if (updateHistory) {
            this.updateUrl(query, episodeType, sort, resolvedPage);
        }

        this.ui.showLoading();

        try {
            const url = new URL('/', window.location.origin);
            url.searchParams.set('q', query);
            url.searchParams.set('episode_type', episodeType);
            url.searchParams.set('sort', sort);
            if (resolvedPage > 1) url.searchParams.set('page', resolvedPage);
            url.searchParams.set('ajax', '1');

            const response = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();

            if (data.success) {
                this.lastSearchParams = { query, episodeType, sort, page: resolvedPage, limit: data.limit };
                this.ui.displayResults(data);
            } else {
                this.ui.showError(data.error || 'Search failed');
            }
        } catch (error) {
            this.ui.showError('Network error occurred');
        }
    }

    async loadEpisodes(page = 1, options = { updateHistory: true }) {
        const episodeType = this.elements.episodeTypeSelect ? this.elements.episodeTypeSelect.value : 'all';
        const sort = this.elements.sortSelect ? this.elements.sortSelect.value : 'newest';
        const searchQuery = this.elements.searchInput ? this.elements.searchInput.value.trim() : '';

        if (options.updateHistory) {
            const url = new URL(window.location);
            if (page > 1) url.searchParams.set('page', page);
            else url.searchParams.delete('page');
            window.history.pushState({}, '', url);
        }

        this.ui.showLoading('Loading episodes...');
        this.ui.hideAllResults();

        try {
            const params = new URLSearchParams({
                view: 'episodes',
                episode_type: episodeType,
                sort: sort,
                page: page,
                ajax: '1'
            });
            if (searchQuery) params.set('q', searchQuery);

            const response = await fetch(`/?${params.toString()}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();

            if (data.success) {
                this.ui.displayEpisodeCards(
                    data,
                    (episode) => this.sidebar.open(episode.id), // onCardClick
                    (newPage) => this.loadEpisodes(newPage)     // onPageClick
                );
            } else {
                this.ui.showError(data.error || 'Failed to load episodes');
            }
        } catch (error) {
            this.ui.showError('Failed to load episodes');
        } finally {
            this.ui.hideLoading();
        }
    }

    async handleViewModeChange(newMode) {
        const query = this.elements.searchInput ? this.elements.searchInput.value.trim() : '';
        const episodeType = this.elements.episodeTypeSelect ? this.elements.episodeTypeSelect.value : 'all';
        const sort = this.elements.sortSelect ? this.elements.sortSelect.value : 'relevance';

        const url = new URL(window.location);
        url.searchParams.delete('page');

        if (newMode === 'episodes') {
            url.searchParams.set('view', 'episodes');
            if (episodeType !== 'all') url.searchParams.set('episode_type', episodeType);
            else url.searchParams.delete('episode_type');

            if (sort !== 'newest') url.searchParams.set('sort', sort);
            else url.searchParams.delete('sort');

            if (query) url.searchParams.set('q', query);
            else url.searchParams.delete('q');

            window.history.pushState({}, '', url);
            this.ui.updatePageTitle(query);
            await this.loadEpisodes(1, { updateHistory: false });
        } else {
            url.searchParams.delete('view');
            if (episodeType !== 'all') url.searchParams.set('episode_type', episodeType);
            else url.searchParams.delete('episode_type');

            if (sort !== 'relevance') url.searchParams.set('sort', sort);
            else url.searchParams.delete('sort');

            if (query) {
                url.searchParams.set('q', query);
                window.history.pushState({}, '', url);
                this.ui.updatePageTitle(query);
                await this.performSearch(query, episodeType, sort, 1, { updateHistory: false });
            } else {
                url.searchParams.delete('q');
                window.history.pushState({}, '', url);
                this.ui.updatePageTitle('');
                this.ui.clearResultsButKeepFilters();
            }
        }
    }

    handlePaginationClick(event) {
        if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.button !== 0) return;

        const link = event.target.closest('a[data-page]');
        if (!link) return;

        event.preventDefault();
        const targetPage = parseInt(link.dataset.page, 10);

        if (this.lastSearchParams) {
            this.performSearch(
                this.lastSearchParams.query,
                this.lastSearchParams.episodeType,
                this.lastSearchParams.sort,
                targetPage,
                { updateHistory: true }
            );
        }
    }

    async handleInput(e) {
        const value = e.target.value.trim();
        const isEpisodesView = this.elements.episodesRadio && this.elements.episodesRadio.checked;

        if (value === '') {
            const url = new URL(window.location);
            url.searchParams.delete('q');
            window.history.pushState({}, '', url);
            this.ui.updatePageTitle('');

            if (isEpisodesView) {
                await this.loadEpisodes(1);
            } else {
                this.ui.clearResultsButKeepFilters();
            }
        }
    }

    async triggerAutoSearch() {
        const query = this.elements.searchInput ? this.elements.searchInput.value.trim() : '';
        const isEpisodesView = this.elements.episodesRadio && this.elements.episodesRadio.checked;
        const episodeType = this.elements.episodeTypeSelect ? this.elements.episodeTypeSelect.value : 'all';
        const sort = this.elements.sortSelect ? this.elements.sortSelect.value : 'relevance';

        const url = new URL(window.location);
        if (episodeType !== 'all') url.searchParams.set('episode_type', episodeType);
        else url.searchParams.delete('episode_type');

        if (isEpisodesView) {
            if (sort !== 'newest') url.searchParams.set('sort', sort);
            else url.searchParams.delete('sort');

            url.searchParams.delete('page');
            window.history.pushState({}, '', url);
            this.ui.updatePageTitle(query);
            await this.loadEpisodes(1, { updateHistory: false });
        } else {
            if (sort !== 'relevance') url.searchParams.set('sort', sort);
            else url.searchParams.delete('sort');

            if (query) {
                url.searchParams.delete('page');
                window.history.pushState({}, '', url);
                this.ui.updatePageTitle(query);
                await this.performSearch(query, episodeType, sort, 1, { updateHistory: false });
            }
        }
    }

    updateUrl(query, episodeType, sort, page) {
        const url = new URL(window.location);
        if (query) url.searchParams.set('q', query);
        else url.searchParams.delete('q');

        if (episodeType !== 'all') url.searchParams.set('episode_type', episodeType);
        else url.searchParams.delete('episode_type');

        if (sort !== 'relevance') url.searchParams.set('sort', sort);
        else url.searchParams.delete('sort');

        if (page > 1) url.searchParams.set('page', page);
        else url.searchParams.delete('page');

        url.searchParams.delete('view');
        window.history.pushState({}, '', url);
        this.ui.updatePageTitle(query);
    }

    handleServerSideResults() {
        if (!window.serverSearchData) return;
        const data = window.serverSearchData;

        if (data.query && this.elements.searchInput) this.elements.searchInput.value = data.query;
        if (data.episode_type && this.elements.episodeTypeSelect) this.elements.episodeTypeSelect.value = data.episode_type;
        if (data.sort && this.elements.sortSelect) this.elements.sortSelect.value = data.sort;

        // Check if results are already rendered server-side
        const resultsList = document.getElementById('resultsList');
        const isPreRendered = resultsList && resultsList.children.length > 0;

        if (data.success) {
            this.lastSearchParams = {
                query: data.query,
                episodeType: data.episode_type,
                sort: data.sort,
                page: data.page,
                limit: data.limit
            };
            this.ui.displayResults(data, isPreRendered);
        } else {
            this.ui.showError(data.error || 'Search failed');
        }
    }

    handleServerSideEpisodes() {
        if (!window.serverEpisodeData) return;
        const data = window.serverEpisodeData;

        if (data.filters) {
            if (data.filters.episode_type && this.elements.episodeTypeSelect) this.elements.episodeTypeSelect.value = data.filters.episode_type;
            if (data.filters.sort && this.elements.sortSelect) this.elements.sortSelect.value = data.filters.sort;
            if (data.filters.q && this.elements.searchInput) this.elements.searchInput.value = data.filters.q;
        }

        // Check if episodes are already rendered server-side
        const cardsList = document.getElementById('episodeCardsList');
        const isPreRendered = cardsList && cardsList.children.length > 0;

        if (data.success) {
            if (isPreRendered) {
                // Just show the container and setup pagination click handlers
                this.ui.episodeCards.classList.remove('hidden');
                // Re-attach click handlers to existing cards
                this.attachEpisodeCardHandlers(cardsList);
            } else {
                this.ui.displayEpisodeCards(
                    data,
                    (episode) => this.sidebar.open(episode.id),
                    (newPage) => this.loadEpisodes(newPage)
                );
            }
        } else {
            this.ui.showError(data.error || 'Failed to load episodes');
        }
    }

    attachEpisodeCardHandlers(cardsList) {
        // Attach click handlers to pre-rendered episode cards
        const cards = cardsList.querySelectorAll('a[href*="episode="]');
        cards.forEach(card => {
            card.addEventListener('click', async (e) => {
                e.preventDefault();
                // Extract episode ID from href
                const url = new URL(card.href, window.location.origin);
                const episodeId = url.searchParams.get('episode');
                if (episodeId) {
                    await this.sidebar.open(episodeId);
                }
            });
        });
    }

    handleUrlParameters() {
        const urlParams = new URLSearchParams(window.location.search);
        const view = urlParams.get('view');
        const query = urlParams.get('q');
        const episodeType = urlParams.get('episode_type');
        const sort = urlParams.get('sort');
        const page = parseInt(urlParams.get('page') || '1', 10) || 1;

        if (view === 'episodes') {
            if (this.elements.episodesRadio) this.elements.episodesRadio.checked = true;

            if (episodeType && this.elements.episodeTypeSelect) this.elements.episodeTypeSelect.value = episodeType;
            if (sort && this.elements.sortSelect) this.elements.sortSelect.value = sort;
            if (query && this.elements.searchInput) this.elements.searchInput.value = query;

            this.ui.updatePageTitle(query || '');
            this.loadEpisodes(page, { updateHistory: false });
        } else {
            if (this.elements.transcriptsRadio) this.elements.transcriptsRadio.checked = true;

            if (query) {
                if (this.elements.searchInput) this.elements.searchInput.value = query;
                if (episodeType && this.elements.episodeTypeSelect) this.elements.episodeTypeSelect.value = episodeType;
                if (sort && this.elements.sortSelect) this.elements.sortSelect.value = sort;

                this.ui.updatePageTitle(query);
                this.performSearch(query, episodeType || 'all', sort || 'relevance', page, { updateHistory: false });
            } else {
                this.ui.clearResultsButKeepFilters();
            }
        }
    }
}
