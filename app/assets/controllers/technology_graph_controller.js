import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { storageKey: String };
    static targets = ['node', 'tools', 'query', 'filter', 'count', 'empty', 'overview', 'panel', 'detail', 'goal', 'goalButton', 'domain', 'domainButton', 'results', 'resultCards', 'more', 'clearButton', 'goalBar'];

    connect() {
        if (!this.hasOverviewTarget) return;
        this.nodes = [...this.nodeTargets];
        this.byKey = new Map(this.nodes.map(node => [node.dataset.nodeKey, node]));
        this.homes = new Map(this.nodes.map(node => [node, node.parentElement]));
        this.parents = new Map(this.nodes.map(node => [node.dataset.nodeKey, JSON.parse(node.dataset.parents)]));
        this.requirements = new Map(this.nodes.map(node => [node.dataset.nodeKey, JSON.parse(node.dataset.requirements)]));
        this.activeDomain = 'research';
        this.selected = null;
        this.goalKey = null;
        try { this.goalKey = localStorage.getItem(this.storageKeyValue); } catch { /* Le suivi reste utilisable sans stockage local. */ }
        if (!this.byKey.has(this.goalKey)) this.goalKey = null;
        this.element.classList.add('technology-enhanced');
        this.toolsTargets.forEach(tools => { tools.hidden = false; });
        this.countTarget.hidden = false;
        this.mobile = matchMedia('(max-width: 900px)');
        this.handleMedia = () => this.updatePanelMode();
        this.mobile.addEventListener('change', this.handleMedia);
        this.handleKeys = event => this.panelKeys(event);
        document.addEventListener('keydown', this.handleKeys);
        this.filter();
        this.updateGoal();
    }

    disconnect() {
        this.mobile?.removeEventListener('change', this.handleMedia);
        document.removeEventListener('keydown', this.handleKeys);
    }

    filter() {
        this.close(false);
        this.expanded = false;
        this.updateList();
    }

    updateList() {
        const normalize = value => value.toLocaleLowerCase('fr').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        const query = normalize(this.queryTarget.value.trim());
        const filtered = this.nodes.filter(node => this.filterTargets.every(select => !select.value || node.dataset[select.dataset.filterKey === 'tier' ? 'tier' : 'filterState'] === select.value));
        const matches = filtered.filter(node => query ? normalize(node.dataset.searchText).includes(query) : node.dataset.domain === this.activeDomain);
        const shown = this.expanded ? matches : matches.slice(0, 6);
        this.nodes.forEach(node => {
            const destination = query ? this.resultCardsTarget : this.homes.get(node);
            if (node.parentElement !== destination) destination.append(node);
            node.hidden = !shown.includes(node);
            node.classList.toggle('is-selected', node.dataset.nodeKey === this.selected);
            node.querySelector('summary').setAttribute('aria-expanded', String(node.dataset.nodeKey === this.selected && !this.panelTarget.hidden));
        });
        this.domainTargets.forEach(domain => { domain.hidden = Boolean(query) || domain.dataset.domain !== this.activeDomain; });
        this.domainButtonTargets.forEach(button => {
            button.setAttribute('aria-pressed', String(!query && button.dataset.domain === this.activeDomain));
            button.querySelector('[data-domain-count]').textContent = filtered.filter(node => node.dataset.domain === button.dataset.domain).length;
        });
        this.clearButtonTarget.hidden = !query && this.filterTargets.every(select => select.value === (select.dataset.filterKey === 'tier' ? 'basic' : ''));
        this.resultsTarget.hidden = !query;
        this.countTarget.textContent = `${shown.length} / ${matches.length} ${matches.length === 1 ? 'élément' : 'éléments'}${query ? ' · recherche dans tous les domaines' : ''}`;
        this.emptyTarget.hidden = matches.length !== 0;
        this.moreTarget.hidden = shown.length === matches.length;
        this.moreTarget.textContent = `Voir les ${matches.length - shown.length} autres éléments →`;
    }

    clear() {
        this.queryTarget.value = '';
        this.filterTargets.forEach(select => { select.value = select.dataset.filterKey === 'tier' ? 'basic' : ''; });
        this.filter();
        this.queryTarget.focus();
    }

    chooseDomain(event) {
        this.activeDomain = event.currentTarget.dataset.domain;
        this.queryTarget.value = '';
        this.filter();
    }

    showMore() {
        const previous = new Set(this.nodes.filter(node => !node.hidden));
        this.expanded = true;
        this.updateList();
        this.nodes.find(node => !node.hidden && !previous.has(node))?.querySelector('summary').focus();
    }

    select(event) {
        event.preventDefault();
        this.returnFocus = event.currentTarget;
        this.selectNode(event.currentTarget.closest('.tech-node').dataset.nodeKey);
    }

    selectNode(key) {
        if (!this.byKey.has(key)) return;
        this.selected = key;
        this.renderDetail();
        this.panelTarget.hidden = false;
        this.panelTarget.scrollTop = 0;
        this.element.classList.add('has-selection');
        this.updateList();
        this.updatePanelMode();
        this.detailTarget.querySelector('h2').focus({ preventScroll: !this.mobile.matches });
    }

    close(restoreFocus = true) {
        this.selected = null;
        this.panelTarget.hidden = true;
        this.panelTarget.removeAttribute('aria-modal');
        this.overviewTarget.inert = false;
        this.element.classList.remove('has-selection');
        this.nodes.forEach(node => {
            node.classList.remove('is-selected');
            node.querySelector('summary').setAttribute('aria-expanded', 'false');
        });
        if (restoreFocus) {
            const origin = this.returnFocus;
            if (origin?.isConnected && origin.checkVisibility()) origin.focus();
            else this.queryTarget.focus();
        }
    }

    updatePanelMode() {
        const modal = Boolean(this.selected && !this.panelTarget.hidden && this.mobile.matches);
        this.panelTarget.setAttribute('role', modal ? 'dialog' : 'complementary');
        if (modal) this.panelTarget.setAttribute('aria-modal', 'true');
        else this.panelTarget.removeAttribute('aria-modal');
        this.overviewTarget.inert = modal;
    }

    panelKeys(event) {
        if (!this.selected || this.panelTarget.hidden) return;
        if (event.key === 'Escape') { event.preventDefault(); this.close(); return; }
        if (!this.mobile.matches || event.key !== 'Tab') return;
        const controls = [...this.panelTarget.querySelectorAll('button, a[href], summary')].filter(element => element.checkVisibility());
        const first = controls[0], last = controls.at(-1);
        if (event.shiftKey && (document.activeElement === first || document.activeElement === this.detailTarget.querySelector('h2'))) {
            event.preventDefault(); last?.focus();
        } else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    }

    prerequisitePath(key) {
        const entries = new Map(), visited = new Set();
        const visit = (childKey, inheritedHistory = false) => {
            const historical = inheritedHistory || this.byKey.get(childKey)?.dataset.acquired === 'true';
            const visitKey = `${childKey}|${historical}`;
            if (visited.has(visitKey)) return;
            visited.add(visitKey);
            for (const requirement of this.requirements.get(childKey) || []) {
                const parentKey = this.requirementKey(requirement);
                visit(parentKey, historical);
                const previous = entries.get(parentKey);
                if (!previous || (previous.historical && !historical) || (previous.historical === historical && (requirement.level ?? 0) > (previous.level ?? 0))) entries.set(parentKey, { ...requirement, historical });
            }
        };
        visit(key);
        return entries;
    }

    appendRequirements(parent, entries) {
        const list = document.createElement('ul');
        list.className = 'technology-requirements';
        for (const requirement of entries) {
            const item = document.createElement('li');
            item.dataset.requirementKey = this.requirementKey(requirement);
            item.dataset.state = requirement.historical ? 'historical' : requirement.met ? 'met' : 'missing';
            const state = requirement.historical ? '⋯ Historique' : requirement.met ? '✓ Rempli' : '○ Manquant';
            this.jumpButton(item, this.requirementKey(requirement), requirement.label);
            this.appendText(item, 'span', `${state} · ${requirement.level !== null ? `Niveau ${requirement.current} / ${requirement.level} requis` : 'Accès de déblocage'}`);
            list.append(item);
        }
        parent.append(list);
    }

    renderDetail() {
        const node = this.byKey.get(this.selected), detail = this.detailTarget;
        detail.replaceChildren();
        const title = this.appendText(detail, 'h2', node.dataset.label);
        title.tabIndex = -1;
        this.appendText(detail, 'p', node.querySelector('.tech-node-kind').textContent, 'technology-detail-meta');
        this.appendText(detail, 'p', node.dataset.acquired === 'true' ? 'Accès acquis définitivement.' : node.querySelector('summary small').textContent, 'technology-detail-meta');
        const nextBox = document.createElement('div');
        nextBox.className = 'technology-next';
        this.appendText(nextBox, 'h3', 'Prochaine étape conseillée');
        const next = this.nextStep(this.selected);
        this.appendText(nextBox, 'p', node.dataset.acquired === 'true' ? 'Objectif atteint : accès acquis définitivement.' : next?.text || 'Prérequis atteints : consulter la fiche pour construire ou rechercher cet élément.');
        if (next && this.byKey.has(next.key)) {
            const link = this.byKey.get(next.key).querySelector('.tech-node-open').cloneNode(true);
            link.textContent = 'Ouvrir la fiche de cette étape →';
            nextBox.append(link);
        }
        detail.append(nextBox);
        const follow = this.appendText(detail, 'button', this.goalKey === this.selected ? 'Objectif suivi ✓' : 'Suivre cet objectif', 'primary technology-follow');
        follow.type = 'button';
        follow.addEventListener('click', () => { this.goalKey = this.selected; this.saveGoal(); this.updateGoal(); this.renderDetail(); this.detailTarget.querySelector('.technology-follow').focus(); });
        detail.append(node.querySelector('.tech-node-open').cloneNode(true));
        this.appendText(detail, 'h3', 'Prérequis de déblocage');
        const direct = this.requirements.get(this.selected).map(requirement => ({ ...requirement, historical: node.dataset.acquired === 'true' }));
        if (direct.length) this.appendRequirements(detail, direct);
        else this.appendText(detail, 'p', 'Aucun prérequis.');
        const directKeys = new Set(direct.map(requirement => this.requirementKey(requirement)));
        const ancestors = [...this.prerequisitePath(this.selected)].filter(([key]) => !directKeys.has(key)).map(([, requirement]) => requirement);
        if (ancestors.length) {
            const previous = document.createElement('details');
            previous.className = 'technology-previous';
            this.appendText(previous, 'summary', `Étapes précédentes (${ancestors.length})`);
            this.appendRequirements(previous, ancestors);
            detail.append(previous);
        }
        this.appendText(detail, 'p', 'Conditions du déblocage initial. Les conditions historiques des accès acquis ne sont pas à refaire.', 'technology-detail-meta');
        this.appendText(detail, 'h3', 'Déblocages directs');
        const children = this.nodes.filter(child => this.parents.get(child.dataset.nodeKey).includes(this.selected));
        for (const domain of this.domainTargets) {
            const members = children.filter(child => child.dataset.domain === domain.dataset.domain);
            if (!members.length) continue;
            const group = document.createElement('details');
            group.className = 'technology-unlocks';
            group.dataset.domain = domain.dataset.domain;
            this.appendText(group, 'summary', `${domain.querySelector('h2').textContent} (${members.length})`);
            const unlocks = document.createElement('ul');
            for (const child of members) {
                const item = document.createElement('li');
                const requirement = this.requirements.get(child.dataset.nodeKey).find(requirement => this.requirementKey(requirement) === this.selected);
                this.jumpButton(item, child.dataset.nodeKey, child.dataset.label + (requirement.level !== null ? ` · niveau ${requirement.level} requis` : ''));
                unlocks.append(item);
            }
            group.append(unlocks);
            detail.append(group);
        }
        this.appendText(detail, 'p', children.length ? 'Les autres prérequis de chaque élément restent nécessaires.' : 'Aucun déblocage direct.', 'technology-detail-meta');
    }

    requirementKey(requirement) {
        return `${requirement.group}:${requirement.slug}-${requirement.tier}`;
    }

    nextStep(key, seen = new Set()) {
        if (seen.has(key)) return null;
        seen.add(key);
        const node = this.byKey.get(key);
        if (!node || node.dataset.acquired === 'true') return null;
        for (const requirement of this.requirements.get(key) || []) {
            if (requirement.met) continue;
            const parentKey = this.requirementKey(requirement), parent = this.byKey.get(parentKey);
            if (parent && parent.dataset.acquired !== 'true' && !parent.classList.contains('available')) {
                const earlier = this.nextStep(parentKey, seen);
                if (earlier) return earlier;
            }
            return { key: parentKey, text: requirement.label + (requirement.level !== null ? ` → niveau ${requirement.level} (actuel : ${requirement.current})` : ' → débloquer l’accès') };
        }
        return null;
    }

    appendText(parent, tag, text, className = '') {
        const element = document.createElement(tag);
        element.textContent = text;
        element.className = className;
        parent.append(element);
        return element;
    }

    jumpButton(parent, key, text) {
        const button = this.appendText(parent, 'button', text, 'technology-jump');
        button.type = 'button';
        button.addEventListener('click', () => this.selectNode(key));
    }

    saveGoal() {
        try {
            if (this.goalKey) localStorage.setItem(this.storageKeyValue, this.goalKey);
            else localStorage.removeItem(this.storageKeyValue);
        } catch { /* Une restriction du navigateur ne bloque pas la consultation. */ }
    }

    updateGoal() {
        const node = this.byKey.get(this.goalKey);
        const next = node ? this.nextStep(this.goalKey) : null;
        this.goalTarget.textContent = node ? `${node.dataset.label} · ${node.dataset.acquired === 'true' ? 'accès acquis' : next?.text || 'prérequis atteints'}` : 'Aucun objectif sélectionné';
        this.goalBarTarget.hidden = !node;
        this.goalButtonTargets.forEach(button => { button.hidden = !node; });
    }

    showGoal(event) {
        this.returnFocus = event.currentTarget;
        if (this.goalKey) this.selectNode(this.goalKey);
    }

    removeGoal() {
        this.goalKey = null;
        this.saveGoal();
        this.updateGoal();
        if (this.selected) this.renderDetail();
        this.queryTarget.focus();
    }
}
