import {
    anchorIdFromHash,
    indicatorLabel as resolveIndicatorLabel,
    navigationCurrentPath as resolveNavigationCurrentPath,
    navigationHomePath as resolveNavigationHomePath,
    navigationRootPath as resolveNavigationRootPath,
    normalizedHash,
    parseJsonDataset,
    scrollWindowTo,
    selectorEscape,
} from './navigation-shared';

export default (rootElement) => ({
    rootElement,
    sections: [],
    sectionMap: new Map(),
    sectionNavigationContexts: new Map(),
    sectionAnchorMap: new Map(),
    sectionAnchorPathMap: new Map(),
    loadingPaths: new Set(),
    activePath: '/',
    menuOpen: false,
    heroLogoVisible: false,
    ticking: false,
    lastSectionTop: null,
    historySettleTimer: null,
    init() {
        this.sections = Array.from(this.rootElement.querySelectorAll('.onepager-section'));
        this.sectionMap = new Map(this.sections.map((section) => [section.dataset.path, section]));
        this.sectionNavigationContexts = new Map(this.sections.map((section) => [
            section.dataset.path,
            parseJsonDataset(section.dataset.navigation, null),
        ]));
        this.sectionAnchorMap = new Map(this.sections
            .filter((section) => Boolean(section.dataset.anchor))
            .map((section) => [section.dataset.path, section.dataset.anchor]));
        this.sectionAnchorPathMap = new Map(this.sections
            .filter((section) => Boolean(section.dataset.anchor))
            .map((section) => [section.dataset.anchor, section.dataset.path]));
        this.contentEndpoint = this.rootElement.dataset.contentEndpoint;
        this.activePath = this.pathForLocation(window.location.pathname || '/', window.location.hash)
            || window.location.pathname
            || '/';
        this.updateHeroLogoVisibility();

        this.loadingObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                void this.loadSection(entry.target.dataset.path);
            });
        }, {
            rootMargin: '320px 0px',
            threshold: 0.01,
        });

        this.sections.forEach((section) => this.loadingObserver.observe(section));
        this.prefetchAdjacent(this.activePath);
        this.updateDocumentTitle(this.titleForPath(this.activePath));

        window.requestAnimationFrame(async () => {
            const aliasedSectionPath = this.sectionPathFromHash(window.location.hash);

            if (aliasedSectionPath !== null) {
                await this.goToSection(aliasedSectionPath, false, 'auto', true);
                this.trackSectionTop();

                return;
            }

            await this.goToSection(this.activePath, false, 'auto', true);
            await this.handleHashNavigation(window.location.hash, this.activePath, 'auto');
            this.trackSectionTop();
        });
    },
    sectionNavigationContext(path) {
        return this.sectionNavigationContexts.get(path) ?? null;
    },
    currentNavigationContext() {
        return this.sectionNavigationContext(this.activePath);
    },
    showBreadcrumbs() {
        return this.activePath !== '/';
    },
    currentBreadcrumbItems() {
        return [];
    },
    currentSectionLabel() {
        const section = this.sectionMap.get(this.activePath);

        return section?.dataset.label || '';
    },
    currentIndicatorLabel() {
        return resolveIndicatorLabel(
            this.currentNavigationContext(),
            null,
            this.currentSectionLabel(),
        );
    },
    currentNavigationRootPath() {
        return resolveNavigationRootPath(this.currentNavigationContext(), this.activePath || '/');
    },
    currentNavigationPath() {
        return resolveNavigationCurrentPath(this.currentNavigationContext(), this.activePath || '/');
    },
    homePath() {
        return resolveNavigationHomePath(this.currentNavigationContext());
    },
    showLogo() {
        return this.activePath !== '/' || !this.heroLogoVisible;
    },
    toggleMenu() {
        this.menuOpen = !this.menuOpen;
    },
    closeMenu() {
        this.menuOpen = false;
    },
    pathForLocation(pathname, hash = '') {
        if (pathname === '/') {
            return this.sectionPathFromHash(hash) || pathname;
        }

        return pathname;
    },
    sectionPathFromHash(hash) {
        const anchorId = anchorIdFromHash(hash);

        if (!anchorId) {
            return null;
        }

        return this.sectionAnchorPathMap.get(anchorId) ?? null;
    },
    sectionAnchorForPath(path) {
        return this.sectionAnchorMap.get(path) ?? null;
    },
    hrefForPath(path) {
        const anchor = this.sectionAnchorMap.get(path);

        return anchor ? `/#${anchor}` : path;
    },
    handlesSectionPath(path) {
        return this.sectionMap.has(path) && this.hrefForPath(path) === path;
    },
    titleForPath(path) {
        const section = this.sectionMap.get(path);

        return section?.dataset.title || document.title;
    },
    updateDocumentTitle(title) {
        if (!title) {
            return;
        }

        document.title = title;
    },
    prefetchAdjacent(path) {
        const index = this.sections.findIndex((s) => s.dataset.path === path);

        if (index === -1) {
            return;
        }

        [this.sections[index - 1], this.sections[index + 1]]
            .filter(Boolean)
            .forEach((section) => {
                void this.loadSection(section.dataset.path);
            });
    },
    async loadSection(path) {
        const section = this.sectionMap.get(path);

        if (!section || section.dataset.loaded === 'true' || this.loadingPaths.has(path)) {
            return;
        }

        this.loadingPaths.add(path);
        section.dataset.loading = 'true';

        try {
            const url = `${this.contentEndpoint}?path=${encodeURIComponent(path)}&presentation=section`;
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`Failed to load section ${path}`);
            }

            section.innerHTML = await response.text();

            // Initialize Alpine (and Livewire components) inside the freshly injected HTML.
            if (window.Alpine) {
                window.Alpine.initTree(section);
            }

            const layoutPreset = response.headers.get('X-Fragment-Layout-Preset');
            if (layoutPreset) {
                layoutPreset.split(/\s+/).filter(Boolean).forEach(cls => section.classList.add(cls));
            }

            const navigation = JSON.parse(response.headers.get('X-Fragment-Navigation') || 'null');

            if (navigation) {
                section.dataset.navigation = JSON.stringify(navigation);
                this.sectionNavigationContexts.set(path, navigation);
            }

            section.dataset.loaded = 'true';
            this.updateHeroLogoVisibility();
        } catch (error) {
            console.error(error);
        } finally {
            this.loadingPaths.delete(path);
            delete section.dataset.loading;
        }
    },
    async goToSection(path, pushState = false, behavior = 'smooth', forceScroll = false) {
        const section = this.sectionMap.get(path);

        if (!section) {
            return;
        }

        await this.loadSection(path);

        if (forceScroll || path !== this.activePath) {
            section.scrollIntoView({
                behavior,
                block: 'start',
            });
        }

        this.activePath = path;
        this.prefetchAdjacent(this.activePath);
        this.updateDocumentTitle(this.titleForPath(this.activePath));

        if (pushState) {
            this.pushHistory(this.activePath);
        }

        window.requestAnimationFrame(() => {
            this.updateHeroLogoVisibility();
        });
    },
    async navigateToSection(path, pushState = true, behavior = 'smooth', hash = '') {
        this.closeMenu();

        if (path !== this.activePath) {
            await this.goToSection(path, false, hash ? 'auto' : behavior);
        }

        const sectionAnchor = this.sectionAnchorForPath(path);
        const normalizedTargetHash = normalizedHash(hash);
        const isSectionAliasHash = sectionAnchor !== null && normalizedTargetHash === `#${sectionAnchor}`;

        await this.handleHashNavigation(isSectionAliasHash ? '' : hash, path, behavior);

        if (pushState) {
            this.pushHistory(path, hash);
        }
    },
    shouldHandleLink(link, event) {
        if (!link || event.defaultPrevented) {
            return false;
        }

        if (link.hasAttribute('download') || link.dataset.noLazy !== undefined) {
            return false;
        }

        if (link.target && link.target !== '_self') {
            return false;
        }

        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return false;
        }

        return true;
    },
    async handleLinkClick(event) {
        const link = event.target.closest('a[href]');

        if (!this.shouldHandleLink(link, event)) {
            return;
        }

        const url = new URL(link.href, window.location.origin);

        if (url.origin !== window.location.origin) {
            return;
        }

        event.preventDefault();

        const handled = await this.tryLazyNavigate(url);

        if (!handled) {
            window.location.assign(url.href);
        }
    },
    async tryLazyNavigate(url) {
        const hash = url.hash || '';
        const aliasedSectionPath = this.sectionPathFromHash(hash);

        if (
            aliasedSectionPath !== null
            && (url.pathname === '/' || url.pathname === window.location.pathname)
        ) {
            await this.navigateToSection(aliasedSectionPath, true, 'smooth', hash);

            return true;
        }

        if (this.handlesSectionPath(url.pathname)) {
            await this.navigateToSection(url.pathname, true, 'smooth', hash);

            return true;
        }

        return false;
    },
    trackSectionTop() {
        const section = this.sectionMap.get(this.activePath);

        if (section) {
            this.lastSectionTop = section.getBoundingClientRect().top;
        }
    },
    onResize() {
        const section = this.sectionMap.get(this.activePath);

        if (!section || this.lastSectionTop === null) {
            return;
        }

        const offsetAfter = section.getBoundingClientRect().top;
        const drift = offsetAfter - this.lastSectionTop;

        if (Math.abs(drift) > 1) {
            window.scrollBy(0, drift);
        }

        this.lastSectionTop = section.getBoundingClientRect().top;
    },
    onScroll() {
        if (this.ticking) {
            return;
        }

        this.ticking = true;

        window.requestAnimationFrame(() => {
            this.determineActivePath();
            this.updateHeroLogoVisibility();
            this.trackSectionTop();
            this.ticking = false;
        });
    },
    heroLogoElement() {
        return this.rootElement.querySelector('[data-path="/"] .hero-logo');
    },
    updateHeroLogoVisibility() {
        const heroLogo = this.heroLogoElement();

        if (!heroLogo) {
            this.heroLogoVisible = false;

            return;
        }

        const rect = heroLogo.getBoundingClientRect();

        this.heroLogoVisible = rect.bottom > 0 && rect.top < window.innerHeight;
    },
    determineActivePath() {
        const viewportCenter = window.innerHeight / 2;
        let nextActivePath = this.activePath;
        let bestDistance = Number.POSITIVE_INFINITY;

        this.sections.forEach((section) => {
            const rect = section.getBoundingClientRect();
            const sectionCenter = rect.top + (rect.height / 2);
            const distance = Math.abs(sectionCenter - viewportCenter);

            if (distance < bestDistance) {
                bestDistance = distance;
                nextActivePath = section.dataset.path || nextActivePath;
            }
        });

        if (nextActivePath !== this.activePath) {
            this.activePath = nextActivePath;
            this.prefetchAdjacent(this.activePath);
            this.updateDocumentTitle(this.titleForPath(this.activePath));

            // Defer history update until scrolling settles to avoid triggering
            // address-bar animations in browsers like Ecosia (iOS WKWebView).
            clearTimeout(this.historySettleTimer);
            this.historySettleTimer = setTimeout(() => {
                this.replaceHistory(this.activePath);
            }, 500);
        }
    },
    async handlePopstate() {
        this.closeMenu();

        const path = window.location.pathname || '/';
        const hash = window.location.hash || '';
        const aliasedSectionPath = this.sectionPathFromHash(hash);

        if (path === '/' && aliasedSectionPath !== null) {
            await this.goToSection(aliasedSectionPath, false, 'auto', true);

            return;
        }

        if (!this.handlesSectionPath(path)) {
            return;
        }

        await this.goToSection(path, false, 'auto', true);
        await this.handleHashNavigation(hash, path, 'auto');
    },
    pushHistory(path, hash = '') {
        window.history.pushState({ path, hash }, '', this.historyUrl(path, hash));
    },
    replaceHistory(path, hash = '') {
        window.history.replaceState({ path, hash }, '', this.historyUrl(path, hash));
    },
    historyUrl(path, hash = '') {
        const sectionAnchor = this.sectionAnchorForPath(path);
        const currentHash = normalizedHash(hash);

        if (sectionAnchor !== null && (!currentHash || currentHash === `#${sectionAnchor}`)) {
            return this.hrefForPath(path);
        }

        return `${path}${currentHash}`;
    },
    sectionTargetElement(path, hash) {
        const anchorId = anchorIdFromHash(hash);
        const section = this.sectionMap.get(path);

        if (!anchorId || !section) {
            return null;
        }

        return section.querySelector(`#${selectorEscape(anchorId)}`);
    },
    async handleHashNavigation(hash, path, behavior = 'smooth') {
        const currentHash = normalizedHash(hash);

        if (!currentHash || !this.sectionMap.has(path)) {
            return false;
        }

        await this.loadSection(path);

        const target = this.sectionTargetElement(path, currentHash);

        if (!target) {
            return false;
        }

        scrollWindowTo(target, behavior);

        return true;
    },
});
