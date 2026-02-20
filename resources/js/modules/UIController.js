export class UIController {
    constructor() {
        this.resultsList = document.getElementById('resultsList');
        this.paginationContainer = document.getElementById('pagination');
        this.loadingState = document.getElementById('loadingState');
        this.resultsContainer = document.getElementById('results');
        this.noResults = document.getElementById('noResults');
        this.errorState = document.getElementById('errorState');
        this.welcomeState = document.getElementById('welcomeState');
        this.episodeCards = document.getElementById('episodeCards');

        // Modals
        this.infoModal = document.getElementById('infoModal');
        this.infoModalBackdrop = document.getElementById('infoModalBackdrop');
        this.episodeInfoModal = document.getElementById('episodeInfoModal');
        this.episodeInfoModalBackdrop = document.getElementById('episodeInfoModalBackdrop');
        this.shareToast = document.getElementById('shareToast');

        // Setup Escape key handler for modals
        this.setupEscapeKeyHandler();
    }

    setupEscapeKeyHandler() {
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                if (this.infoModal && this.infoModal.classList.contains('open')) {
                    this.closeInfoModal();
                }
                if (this.episodeInfoModal && this.episodeInfoModal.classList.contains('open')) {
                    this.closeEpisodeInfoModal();
                }
            }
        });
    }

    toggleModal(modal, backdrop, isOpen) {
        if (modal) modal.classList.toggle('open', isOpen);
        if (backdrop) backdrop.classList.toggle('open', isOpen);
    }

    openInfoModal() {
        this.toggleModal(this.infoModal, this.infoModalBackdrop, true);
    }

    closeInfoModal() {
        this.toggleModal(this.infoModal, this.infoModalBackdrop, false);
    }

    openEpisodeInfoModal() {
        this.toggleModal(this.episodeInfoModal, this.episodeInfoModalBackdrop, true);
    }

    closeEpisodeInfoModal() {
        this.toggleModal(this.episodeInfoModal, this.episodeInfoModalBackdrop, false);
    }

    shareSidebarUrl() {
        const url = window.location.href;
        const showToast = (msg) => this.showShareToast(msg);

        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(
                () => showToast('Link copied to clipboard!'),
                () => showToast('Failed to copy link')
            );
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = url;
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                showToast('Link copied to clipboard!');
            } catch (e) {
                showToast('Failed to copy link');
            }
            document.body.removeChild(textarea);
        }
    }

    showShareToast(message) {
        if (!this.shareToast) return;
        this.shareToast.textContent = message;
        this.shareToast.classList.remove('opacity-0', 'pointer-events-none');
        this.shareToast.classList.add('opacity-100');
        setTimeout(() => {
            this.shareToast.classList.remove('opacity-100');
            this.shareToast.classList.add('opacity-0', 'pointer-events-none');
        }, 1800);
    }

    showLoading(message = 'Searching yesterdays...') {
        this.resultsContainer.classList.add('hidden');
        this.episodeCards.classList.add('hidden');
        this.noResults.classList.add('hidden');
        this.errorState.classList.add('hidden');
        this.welcomeState.classList.add('hidden');
        this.loadingState.classList.remove('hidden');

        const loadingText = this.loadingState.querySelector('p');
        if (loadingText) loadingText.textContent = message;

        this.clearHeader();
        if (this.paginationContainer) this.paginationContainer.innerHTML = '';

        this.setButtonLoading(true);
    }

    hideLoading() {
        this.loadingState.classList.add('hidden');
        this.setButtonLoading(false);
    }

    setButtonLoading(isLoading) {
        const button = document.querySelector('button[type="submit"]');
        if (!button) return;

        const searchText = button.querySelector('.search-text');
        const loading = button.querySelector('.loading');

        if (isLoading) {
            searchText.classList.add('hidden');
            loading.classList.remove('hidden');
            loading.style.display = 'inline-block';
            button.disabled = true;
        } else {
            searchText.classList.remove('hidden');
            loading.classList.add('hidden');
            loading.style.display = 'none';
            button.disabled = false;
        }
    }

    showError(message) {
        this.hideLoading();
        this.resultsContainer.classList.add('hidden');
        this.noResults.classList.add('hidden');
        this.welcomeState.classList.add('hidden');

        document.getElementById('errorMessage').textContent = message;
        this.errorState.classList.remove('hidden');
    }

    clearResults() {
        this.resultsContainer.classList.add('hidden');
        this.noResults.classList.add('hidden');
        this.errorState.classList.add('hidden');
        this.loadingState.classList.add('hidden');
        this.welcomeState.classList.remove('hidden');

        document.getElementById('searchInput').value = '';
        this.clearHeader();
        if (this.paginationContainer) this.paginationContainer.innerHTML = '';

        this.updatePageTitle('');
    }

    clearResultsButKeepFilters() {
        this.resultsContainer.classList.add('hidden');
        this.episodeCards.classList.add('hidden');
        this.noResults.classList.add('hidden');
        this.errorState.classList.add('hidden');
        this.loadingState.classList.add('hidden');
        this.welcomeState.classList.remove('hidden');

        this.clearHeader();
        if (this.paginationContainer) this.paginationContainer.innerHTML = '';
    }

    clearHeader() {
        const header = document.getElementById('resultsHeader');
        if (header) {
            header.querySelector('h2').innerHTML = '';
            header.querySelector('p').innerHTML = '';
        }
    }

    updatePageTitle(query = '') {
        const baseTitle = 'Everything Is Showbiz - What Did You Do Yesterday?';
        const episodesToggle = document.getElementById('episodes');
        const isEpisodesView = episodesToggle && episodesToggle.checked;

        if (isEpisodesView) {
            document.title = query && query.trim()
                ? `${query.trim()} (Episodes) - Everything Is Showbiz`
                : `Episode Archive - Everything Is Showbiz`;
        } else {
            document.title = query && query.trim()
                ? `${query.trim()}: ${baseTitle}`
                : baseTitle;
        }
    }

    displayResults(data, skipRendering = false) {
        this.hideLoading();
        this.noResults.classList.add('hidden');
        this.errorState.classList.add('hidden');
        this.welcomeState.classList.add('hidden');

        const shownCount = Array.isArray(data.results) ? data.results.length : 0;
        this.updateResultsHeader(data, shownCount);

        // If content is already rendered server-side, just show the container
        if (skipRendering) {
            this.resultsContainer.classList.remove('hidden');
            this.generatePagination(data);
            return;
        }

        if (this.resultsList) this.resultsList.innerHTML = '';

        if (shownCount === 0) {
            this.noResults.classList.remove('hidden');
            return;
        }

        if (this.resultsList) {
            this.resultsList.innerHTML = data.results.map(result => `
                <div class="result-card clickable-result bg-white rounded-lg border border-gray-200 p-6 relative hover:shadow-md transition-shadow"
                     onclick="window.app.sidebar.open(${result.episode_id}, ${result.id})"
                     data-episode-id="${result.episode_id}"
                     data-segment-id="${result.id}">
                    <div class="mb-4">
                        <div class="text-gray-900 text-lg leading-relaxed font-medium">
                            ${/* highlighted_text is pre-escaped by PHP (htmlspecialchars) before <mark> tags are applied, so innerHTML is safe here */ result.highlighted_text}
                        </div>
                    </div>
                    <div class="mb-3 text-sm text-gray-700">
                        <span>${result.formatted_date}</span>
                        <span class="mx-2">•</span>
                        <span>${result.formatted_time} - ${result.formatted_end_time}</span>
                    </div>
                    <div class="text-sm text-gray-700">
                        <span class="italic font-medium">${result.episode_title}</span>
                        <span class="mx-2">•</span>
                        <span class="text-gray-500 italic">
                            ${result.episode_type === 'midweek_mayhem' ? 'Midweek Mayhem' : 'Interview'}
                        </span>
                    </div>
                </div>
            `).join('');
        }

        this.resultsContainer.classList.remove('hidden');
        this.generatePagination(data);
    }

    hideAllResults() {
        this.resultsContainer.classList.add('hidden');
        this.episodeCards.classList.add('hidden');
        this.noResults.classList.add('hidden');
        this.errorState.classList.add('hidden');
        this.welcomeState.classList.add('hidden');
        this.loadingState.classList.add('hidden');
    }

    displayEpisodeCards(data, onCardClick, onPageClick) {
        this.hideAllResults();

        const cardsList = document.getElementById('episodeCardsList');
        const pagination = document.getElementById('episodePagination');

        if (cardsList) cardsList.innerHTML = '';
        if (pagination) pagination.innerHTML = '';

        if (!data.episodes || data.episodes.length === 0) {
            this.noResults.classList.remove('hidden');
            return;
        }

        if (cardsList) {
            data.episodes.forEach(episode => {
                const card = this.createEpisodeCard(episode, onCardClick);
                cardsList.appendChild(card);
            });
        }

        if (pagination && data.pagination && data.pagination.last_page > 1) {
            this.createEpisodePagination(data.pagination, pagination, onPageClick);
        }

        this.episodeCards.classList.remove('hidden');
    }

    createEpisodeCard(episode, onCardClick) {
        const card = document.createElement('a');
        card.href = `/?view=episodes&episode=${episode.id}`;
        card.className = 'block bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow cursor-pointer text-left';
        card.onclick = (e) => {
            e.preventDefault();
            if (onCardClick) onCardClick(episode);
        };

        card.innerHTML = `
            <div class="mb-3">
                <h3 class="text-lg font-semibold text-gray-900 mb-2 line-clamp-2">${episode.title}</h3>
                <div class="flex items-center text-sm text-gray-500 mb-2">
                    <span>${episode.formatted_date}</span>
                    ${episode.episode_type === 'midweek_mayhem' ? `<span class="mx-2">•</span><span>Midweek Mayhem</span>` : ''}
                </div>
            </div>
            ${episode.short_description ? `
                <p class="text-gray-700 text-sm line-clamp-3 mb-4">${episode.short_description}</p>
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

    createEpisodePagination(pagination, container, onPageClick) {
        const nav = document.createElement('nav');
        nav.className = 'flex items-center justify-between w-full';

        const buttons = document.createElement('div');
        buttons.className = 'flex space-x-2 mx-auto';

        // Previous
        if (pagination.current_page > 1) {
            const prevBtn = document.createElement('a');
            prevBtn.href = '#';
            prevBtn.className = 'pagination-button';
            prevBtn.textContent = 'Previous';
            prevBtn.onclick = (e) => {
                e.preventDefault();
                if (onPageClick) onPageClick(pagination.current_page - 1);
            };
            buttons.appendChild(prevBtn);
        } else {
            const prevBtn = document.createElement('span');
            prevBtn.className = 'pagination-button pagination-button-disabled';
            prevBtn.textContent = 'Previous';
            buttons.appendChild(prevBtn);
        }

        // Next
        if (pagination.current_page < pagination.last_page) {
            const nextBtn = document.createElement('a');
            nextBtn.href = '#';
            nextBtn.className = 'pagination-button';
            nextBtn.textContent = 'Next';
            nextBtn.onclick = (e) => {
                e.preventDefault();
                if (onPageClick) onPageClick(pagination.current_page + 1);
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

    updateResultsHeader(data, shownCount) {
        const header = document.getElementById('resultsHeader');
        if (!header) return;

        const titleEl = header.querySelector('h2');
        const metaEl = header.querySelector('p');
        if (!titleEl || !metaEl) return;

        const total = typeof data.total === 'number' ? data.total : shownCount;
        const isEstimated = data.is_estimated === true;
        const page = Math.max(parseInt(data.page ?? 1, 10) || 1, 1);
        let limit = parseInt(data.limit ?? shownCount, 10);

        if (!Number.isFinite(limit) || limit <= 0) {
            limit = shownCount > 0 ? shownCount : (total > 0 ? total : 1);
        }

        const remaining = Math.max(total - (page - 1) * limit, 0);
        const effectiveShown = shownCount > 0 ? shownCount : Math.min(limit, remaining);

        // Display "500+" for estimated large result sets
        const totalDisplay = isEstimated ? '500+' : total.toLocaleString();

        const resultWord = total === 1 ? 'result' : 'results';

        if (total <= 0) {
            titleEl.textContent = 'Found 0 results';
        } else if (total <= limit && page === 1) {
            titleEl.textContent = `Found ${totalDisplay} ${resultWord}`;
        } else if (effectiveShown <= 0) {
            titleEl.textContent = `Found ${totalDisplay} ${resultWord}`;
        } else {
            const first = ((page - 1) * limit) + 1;
            const last = first + effectiveShown - 1;
            titleEl.textContent = `Showing ${first.toLocaleString()} - ${last.toLocaleString()} of ${totalDisplay} ${resultWord}`;
        }

        const sortLabels = window.searchLabels?.sort || { relevance: 'Sorted by relevance', newest: 'Newest first', oldest: 'Oldest first' };
        const typeLabels = window.searchLabels?.type || { all: 'All episode types', interview: 'Interviews only', midweek_mayhem: 'Midweek Mayhem only' };

        const sortKey = (data.sort ?? 'relevance').toString().toLowerCase();
        const episodeTypeKey = (data.episode_type ?? 'all').toString().toLowerCase();

        const summaryParts = [];
        if (typeLabels[episodeTypeKey]) summaryParts.push(typeLabels[episodeTypeKey]);
        if (sortLabels[sortKey]) summaryParts.push(sortLabels[sortKey]);

        metaEl.textContent = summaryParts.join(' • ');
    }

    generatePagination(data) {
        if (!this.paginationContainer) return;

        const total = typeof data.total === 'number' ? data.total : 0;
        let limit = parseInt(data.limit, 10);
        if (!Number.isFinite(limit) || limit <= 0) {
            if (total > 0) limit = total;
            else {
                this.paginationContainer.innerHTML = '';
                return;
            }
        }

        const totalPages = limit > 0 ? Math.max(Math.ceil(total / limit), 1) : 1;
        const currentPage = Math.max(parseInt(data.page, 10) || 1, 1);

        if (totalPages <= 1) {
            this.paginationContainer.innerHTML = '';
            return;
        }

        const query = data.query ?? '';
        const episodeType = data.episode_type ?? 'all';
        const sort = data.sort ?? 'relevance';

        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);

        let html = '<nav class="flex items-center space-x-2">';

        // Previous
        if (currentPage > 1) {
            html += `<a href="#" data-page="${currentPage - 1}" class="pagination-button">Previous</a>`;
        } else {
            html += `<span class="pagination-button pagination-button-disabled">Previous</span>`;
        }

        // First page
        if (startPage > 1) {
            html += `<a href="#" data-page="1" class="pagination-button">1</a>`;
            if (startPage > 2) html += '<span class="pagination-ellipsis">…</span>';
        }

        // Pages
        for (let page = startPage; page <= endPage; page++) {
            if (page === currentPage) {
                html += `<span class="pagination-button pagination-button-active">${page}</span>`;
            } else {
                html += `<a href="#" data-page="${page}" class="pagination-button">${page}</a>`;
            }
        }

        // Last page
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) html += '<span class="pagination-ellipsis">…</span>';
            html += `<a href="#" data-page="${totalPages}" class="pagination-button">${totalPages}</a>`;
        }

        // Next
        if (currentPage < totalPages) {
            html += `<a href="#" data-page="${currentPage + 1}" class="pagination-button">Next</a>`;
        } else {
            html += `<span class="pagination-button pagination-button-disabled">Next</span>`;
        }

        html += '</nav>';
        this.paginationContainer.innerHTML = html;
    }
}
