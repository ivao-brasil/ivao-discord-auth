document.addEventListener('alpine:init', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const locale = document.documentElement.lang;

    const format = (text, values = {}) =>
        Object.entries(values).reduce((result, [key, value]) => result.replaceAll(`:${key}`, value), text);

    const newRule = () => ({
        id: crypto.getRandomValues(new Uint32Array(1))[0].toString(16),
        name: '',
        roles: [],
        staff: [],
        includeTrial: true,
        divisionMode: 'any',
        divisions: [],
        minAtcRating: null,
        minPilotRating: null,
        minHours: null,
        requiresGca: false,
        requiresVaOwnership: false,
    });

    Alpine.data('admin', (settings) => ({
        t: settings.text,
        settings,
        page: location.hash === '#members' ? 'members' : 'rules',
        toast: null,

        rules: [],
        roles: [],
        rulesLoading: true,
        rulesError: null,

        members: [],
        membersTotal: 0,
        nextPage: null,
        membersLoading: false,
        membersError: null,
        search: '',
        statusFilter: null,

        syncQueued: false,

        sheet: null,
        sheetError: null,
        busy: false,

        draft: null,
        draftIsNew: false,
        divisionsText: '',
        roleSearch: '',
        positionInput: '',
        confirmDelete: false,

        member: null,
        memberSummary: null,
        confirmRemove: false,

        init() {
            this.loadRules();
            this.loadMembers();

            this.$watch('page', (page) => history.replaceState(null, '', page === 'members' ? '#members' : location.pathname));
            this.$watch('search', Alpine.debounce(() => this.loadMembers(), 300));
            this.$watch('statusFilter', () => this.loadMembers());
        },

        format,

        async request(method, url, body) {
            const response = await fetch(url, {
                method,
                redirect: 'manual',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: body === undefined ? undefined : JSON.stringify(body),
            });

            // The session expired and the login redirect cannot be followed from a background request
            if (response.type === 'opaqueredirect') {
                location.reload();
                throw new Error(this.t.errors.generic);
            }

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(data.message || this.t.errors.generic);
            }

            return data;
        },

        notify(message) {
            this.toast = message;
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => (this.toast = null), 3200);
        },

        formatDate(value, withTime = false) {
            if (!value) {
                return '';
            }

            return new Intl.DateTimeFormat(locale, withTime ? { dateStyle: 'short', timeStyle: 'short' } : { dateStyle: 'short' })
                .format(new Date(value));
        },

        closeSheet() {
            if (this.busy) {
                return;
            }

            this.sheet = null;
            this.sheetError = null;
            this.confirmDelete = false;
            this.confirmRemove = false;
        },

        /* Rules */

        async loadRules() {
            this.rulesLoading = true;
            this.rulesError = null;

            try {
                [this.rules, this.roles] = await Promise.all([
                    this.request('GET', '/api/admin/rules'),
                    this.request('GET', '/api/admin/roles'),
                ]);
            } catch (error) {
                this.rulesError = error.message;
            } finally {
                this.rulesLoading = false;
            }
        },

        get rulesCount() {
            return this.rules.length === 1 ? this.t.rules.countOne : format(this.t.rules.count, { count: this.rules.length });
        },

        get syncTitle() {
            const lastSync = this.settings.lastSync;

            return lastSync ? format(this.t.sync.last, { date: this.formatDate(lastSync.finishedAt, true) }) : this.t.sync.never;
        },

        get syncDetail() {
            const lastSync = this.settings.lastSync;

            if (!lastSync) {
                return format(this.t.sync.schedule, { time: this.settings.syncTime });
            }

            return format(this.t.sync.summary, lastSync) + (lastSync.failed ? format(this.t.sync.failed, lastSync) : '');
        },

        async syncEveryone() {
            try {
                await this.request('POST', '/api/admin/sync');
                this.syncQueued = true;
                this.notify(this.t.sync.queuedToast);
            } catch (error) {
                this.notify(error.message);
            }
        },

        role(id) {
            return this.roles.find((role) => role.id === id) ?? { id, name: id, color: null, assignable: false };
        },

        roleColor(id) {
            return this.role(id).color ?? '#99AAB5';
        },

        ruleSummary(rule) {
            const { rules: t } = this.t;
            const parts = [];

            if (rule.staff.length) {
                parts.push(rule.staff.join(', ') + (rule.includeTrial ? '' : ` (${t.summaryNoTrial})`));
            } else {
                parts.push(t.summaryEveryone);
            }

            if (rule.divisionMode === 'in') {
                parts.push(format(t.summaryOnly, { divisions: rule.divisions.join(', ') }));
            } else if (rule.divisionMode === 'not_in') {
                parts.push(format(t.summaryExcept, { divisions: rule.divisions.join(', ') }));
            }

            if (rule.minAtcRating) {
                parts.push(`${this.settings.ratings.atc[rule.minAtcRating]}+`);
            }

            if (rule.minPilotRating) {
                parts.push(`${this.settings.ratings.pilot[rule.minPilotRating]}+`);
            }

            if (rule.minHours) {
                parts.push(format(t.summaryHours, { hours: rule.minHours }));
            }

            if (rule.requiresGca) {
                parts.push(t.requiresGca);
            }

            if (rule.requiresVaOwnership) {
                parts.push(t.requiresVaOwnership);
            }

            return parts.join(' · ');
        },

        openRule(rule = null) {
            this.draftIsNew = rule === null;
            this.draft = rule === null ? newRule() : structuredClone(Alpine.raw(rule));
            this.draft.minAtcRating ??= '';
            this.draft.minPilotRating ??= '';
            this.divisionsText = this.draft.divisions.join(', ');
            this.roleSearch = '';
            this.positionInput = '';
            this.sheetError = null;
            this.confirmDelete = false;
            this.sheet = 'rule';
        },

        get filteredRoles() {
            const search = this.roleSearch.trim().toLowerCase();

            const roles = search ? this.roles.filter((role) => role.name.toLowerCase().includes(search)) : this.roles;

            // Roles that cannot be assigned are listed after the usable ones
            return [...roles.filter((role) => role.assignable), ...roles.filter((role) => !role.assignable)];
        },

        roleHint(role) {
            if (role.reason === 'administrator') {
                return this.t.rules.administratorRole;
            }

            if (role.reason === 'managed') {
                return this.t.rules.managedRole;
            }

            return role.aboveBot ? this.t.rules.aboveBot : '';
        },

        toggleRole(role) {
            if (!role.assignable) {
                return;
            }

            const roles = this.draft.roles;
            this.draft.roles = roles.includes(role.id) ? roles.filter((id) => id !== role.id) : [...roles, role.id];
        },

        addPositions() {
            const positions = this.positionInput.toUpperCase().split(/[\s,;:]+/).filter(Boolean);
            this.draft.staff = [...new Set([...this.draft.staff, ...positions])];
            this.positionInput = '';
        },

        removePosition(position) {
            this.draft.staff = this.draft.staff.filter((item) => item !== position);
        },

        stepHours(amount) {
            const hours = (this.draft.minHours ?? 0) + amount;
            this.draft.minHours = hours > 0 ? hours : null;
        },

        async saveRules(rules, message) {
            this.busy = true;
            this.sheetError = null;

            try {
                this.rules = await this.request('PUT', '/api/admin/rules', { rules });
                this.busy = false;
                this.closeSheet();
                this.notify(message);
            } catch (error) {
                this.sheetError = error.message;
            } finally {
                this.busy = false;
            }
        },

        saveRule() {
            this.addPositions();
            this.draft.name = this.draft.name.trim();
            this.draft.divisions = this.divisionsText.toUpperCase().split(/[\s,;]+/).filter(Boolean);
            this.draft.minAtcRating = Number(this.draft.minAtcRating) || null;
            this.draft.minPilotRating = Number(this.draft.minPilotRating) || null;

            if (!this.draft.name || this.draft.roles.length === 0) {
                this.sheetError = this.t.errors.ruleIncomplete;
                return;
            }

            const rules = this.draftIsNew
                ? [...this.rules, this.draft]
                : this.rules.map((rule) => (rule.id === this.draft.id ? this.draft : rule));

            this.saveRules(rules, this.t.rules.saved);
        },

        deleteRule() {
            this.saveRules(this.rules.filter((rule) => rule.id !== this.draft.id), this.t.rules.saved);
        },

        /* Members */

        async loadMembers(page = 1) {
            this.membersLoading = true;
            this.membersError = null;

            const params = new URLSearchParams({ page });
            if (this.search.trim()) {
                params.set('q', this.search.trim());
            }
            if (this.statusFilter) {
                params.set('status', this.statusFilter);
            }

            try {
                const data = await this.request('GET', `/api/admin/members?${params}`);
                this.members = page === 1 ? data.members : [...this.members, ...data.members];
                this.membersTotal = data.total;
                this.nextPage = data.nextPage;
            } catch (error) {
                this.membersError = error.message;
            } finally {
                this.membersLoading = false;
            }
        },

        get membersCount() {
            return this.membersTotal === 1 ? this.t.members.countOne : format(this.t.members.count, { count: this.membersTotal });
        },

        statusLabel(status) {
            return status ? this.t.members.status[status] : '';
        },

        async openMember(summary) {
            this.memberSummary = summary;
            this.member = null;
            this.sheetError = null;
            this.confirmRemove = false;
            this.sheet = 'member';

            try {
                this.member = await this.request('GET', `/api/admin/members/${summary.id}`);
            } catch (error) {
                this.sheetError = error.message;
            }
        },

        get memberHasChanges() {
            const changes = this.member?.changes;

            return Boolean(changes && (changes.add.length || changes.remove.length || changes.nickname));
        },

        async syncMember() {
            this.busy = true;
            this.sheetError = null;

            try {
                const result = await this.request('POST', `/api/admin/members/${this.memberSummary.id}/sync`);
                this.memberSummary.status = result.away ? 'away' : result.skipped ? 'pending' : 'ok';
                if (result.nickname) {
                    this.memberSummary.nickname = result.nickname;
                }
                this.notify(result.skipped ? this.t.members.syncedSkipped : this.t.members.synced);
                this.member = await this.request('GET', `/api/admin/members/${this.memberSummary.id}`);
            } catch (error) {
                this.sheetError = error.message;
            } finally {
                this.busy = false;
            }
        },

        async removeMember() {
            this.busy = true;
            this.sheetError = null;

            try {
                await this.request('DELETE', `/api/admin/members/${this.memberSummary.id}`);
                this.members = this.members.filter((member) => member.id !== this.memberSummary.id);
                this.membersTotal--;
                this.busy = false;
                this.closeSheet();
                this.notify(this.t.members.removed);
            } catch (error) {
                this.sheetError = error.message;
            } finally {
                this.busy = false;
            }
        },
    }));
});
