document.addEventListener('alpine:init', () => {
    Alpine.data('cvBuilder', (config) => ({
        state: JSON.parse(document.getElementById('cv-builder-state').textContent),
        step: config.step,
        saveUrl: config.saveUrl,
        editUrl: config.editUrl,
        photoUrl: config.photoUrl,
        photoView: config.photoView,
        themePreviewUrl: config.themePreviewUrl,
        previewUrl: config.previewUrl,
        pageCountUrl: config.pageCountUrl,
        downloadUrl: config.downloadUrl,
        themes: config.themes,
        sectionLabels: config.sectionLabels,
        messages: config.messages,
        tokenName: config.tokenName,
        token: config.token,
        status: '',
        errors: {},
        photoMessage: '',
        selectedTheme: '',
        previewOpen: false,
        previewTrigger: null,
        previewFrameUrl: '',
        pageCountMessage: '',
        busy: false,
        dirty: false,
        timer: null,

        init() {
            this.state.profile ??= { full_name: '', headline: '', email: '', phone: '', city: '', country: '' };
            this.state.profile.summary ??= '';
            for (const key of ['links', 'experiences', 'educations', 'skill_groups', 'projects', 'certifications', 'organizations', 'awards', 'languages', 'custom_sections']) {
                this.state[key] ??= [];
            }
            this.state.experiences = this.state.experiences.map((row) => ({ ...row, bullets_text: (row.bullets ?? []).join('\n') }));
            this.state.educations = this.state.educations.map((row) => ({ ...row }));
            this.state.skill_groups = this.state.skill_groups.map((row) => ({ ...row, items_text: (row.items ?? []).join('\n') }));
            for (const key of ['projects', 'organizations']) {
                this.state[key] = this.state[key].map((row) => ({ ...row, bullets_text: (row.bullets ?? []).join('\n') }));
            }
            this.state.projects = this.state.projects.map((row) => ({ ...row, tech_stack_text: (row.tech_stack ?? []).join('\n') }));
            this.state.custom_sections = this.state.custom_sections.map((row) => ({ ...row, items_text: (row.items ?? []).join('\n') }));
            this.state.cv.title ??= '';
            this.state.cv.paper_size ??= 'a4';
            this.state.cv.font_scale ??= 'medium';
            this.selectedTheme = this.state.cv.theme_key ?? '';
            this.state.sections = this.state.sections.length
                ? this.state.sections
                : ['profile', 'summary', 'experience', 'education', 'skills', 'links', 'projects', 'certifications', 'organizations', 'awards', 'languages', 'custom_sections']
                    .map((section_key, sort_order) => ({ section_key, is_visible: true, sort_order }));
            if (this.step === 8) {
                const palette = this.selectedPalette();
                this.state.cv.accent_color ??= palette[0];
                this.previewFrameUrl = `${this.previewUrl}?v=${Date.now()}`;
                this.refreshPageCount();
            }
        },

        lines(value) {
            return value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
        },

        rowsWith(rowList, key, value) {
            return rowList.map((row) => ({ ...row, [key]: value(row) }));
        },

        payloadForStep() {
            if (this.step === 1) {
                return {
                    title: this.state.cv.title,
                    profile: this.state.profile,
                    links: this.state.links,
                };
            }
            if (this.step === 2) return { summary: this.state.profile.summary ?? '' };
            if (this.step === 3) return { experiences: this.rowsWith(this.state.experiences, 'bullets', (row) => this.lines(row.bullets_text ?? '')) };
            if (this.step === 4) return { educations: this.state.educations };
            if (this.step === 5) return { skill_groups: this.rowsWith(this.state.skill_groups, 'items', (row) => this.lines(row.items_text ?? '')) };
            if (this.step === 7) return { theme_key: this.selectedTheme };
            if (this.step === 8) {
                return {
                    paper_size: this.state.cv.paper_size,
                    font_scale: this.state.cv.font_scale,
                    accent_color: this.state.cv.accent_color,
                };
            }

            return {
                projects: this.rowsWith(this.rowsWith(this.state.projects, 'bullets', (row) => this.lines(row.bullets_text ?? '')), 'tech_stack', (row) => this.lines(row.tech_stack_text ?? '')),
                certifications: this.state.certifications,
                organizations: this.rowsWith(this.state.organizations, 'bullets', (row) => this.lines(row.bullets_text ?? '')),
                awards: this.state.awards,
                languages: this.state.languages,
                custom_sections: this.rowsWith(this.state.custom_sections, 'items', (row) => this.lines(row.items_text ?? '')),
                sections: this.state.sections,
            };
        },

        scheduleSave() {
            this.dirty = true;
            this.status = 'saving';
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.save(), 600);
        },

        async save() {
            clearTimeout(this.timer);
            if (this.busy) return false;
            this.status = 'saving';
            this.busy = true;
            this.errors = {};
            try {
                do {
                    this.dirty = false;
                    const response = await fetch(this.saveUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.token,
                        },
                        body: JSON.stringify(this.payloadForStep()),
                    });
                    const result = await response.json();
                    if (result.csrf) this.token = result.csrf;
                    if (response.status === 422) {
                        this.errors = result.errors ?? {};
                        this.status = 'error';
                        return false;
                    }
                    if (!response.ok || result.status !== 'saved') {
                        this.status = 'error';
                        return false;
                    }
                } while (this.dirty);

                this.status = 'saved';
                if (this.step === 8) {
                    this.previewFrameUrl = `${this.previewUrl}?v=${Date.now()}`;
                    await this.refreshPageCount();
                }
                return true;
            } catch {
                this.status = 'error';
                return false;
            } finally {
                this.busy = false;
            }
        },

        async navigate(step) {
            if (this.busy) return;
            if (await this.save()) {
                window.location.assign(this.editUrl.replace(/\/step\/\d+$/, `/step/${step}`));
            }
        },

        async downloadPdf() {
            if (this.busy) return;
            if (await this.save()) {
                window.location.assign(this.downloadUrl);
            }
        },

        previewTheme(themeKey) {
            this.previewFrameUrl = this.themePreviewUrl.replace('__theme__', encodeURIComponent(themeKey));
            this.previewTrigger = document.activeElement;
            this.previewOpen = true;
            this.$nextTick(() => this.$refs.previewClose?.focus());
        },

        closePreview() {
            this.previewOpen = false;
            this.$nextTick(() => this.previewTrigger?.focus());
        },

        trapPreviewFocus(event) {
            if (!this.previewOpen || event.key !== 'Tab') return;
            const focusable = [...this.$refs.previewDialog.querySelectorAll('button:not([disabled]), iframe')];
            if (focusable.length === 0) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },

        configurePreviewFrame(event) {
            const frame = event.target;
            const previewDocument = frame.contentDocument;
            if (!previewDocument) return;
            previewDocument.addEventListener('keydown', (keyEvent) => {
                if (keyEvent.key !== 'Tab') return;
                const focusable = [...previewDocument.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')];
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (keyEvent.shiftKey && (!first || previewDocument.activeElement === first || previewDocument.activeElement === previewDocument.body)) {
                    keyEvent.preventDefault();
                    this.$refs.previewClose.focus();
                } else if (!keyEvent.shiftKey && (!last || previewDocument.activeElement === last)) {
                    keyEvent.preventDefault();
                    this.$refs.previewClose.focus();
                }
            });
        },

        selectedPalette() {
            const selected = this.themes.find((theme) => theme.key === this.selectedTheme);
            return selected?.accent_palette ?? [];
        },

        async refreshPageCount() {
            this.pageCountMessage = this.messages.pageCountLoading;
            try {
                const response = await fetch(this.pageCountUrl, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                });
                const result = await response.json();
                if (!response.ok || result.status !== 'ok' || !Number.isInteger(result.page_count) || result.page_count < 1) {
                    this.pageCountMessage = this.messages.pageCountError;
                    return;
                }
                this.pageCountMessage = this.messages.pageCount.replace('{0}', result.page_count);
            } catch {
                this.pageCountMessage = this.messages.pageCountError;
            }
        },

        add(list, row = {}) {
            list.push({ ...row });
        },

        remove(list, index) {
            list.splice(index, 1);
            this.scheduleSave();
        },

        move(list, index, direction) {
            const target = index + direction;
            if (target < 0 || target >= list.length) return;
            [list[index], list[target]] = [list[target], list[index]];
            this.scheduleSave();
        },

        async uploadPhoto(event) {
            const form = event.target;
            const data = new FormData(form);
            data.set(this.tokenName, this.token);
            this.status = 'saving';
            this.busy = true;
            this.photoMessage = '';
            try {
                const response = await fetch(this.photoUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.token },
                    body: data,
                });
                const result = await response.json();
                if (result.csrf) this.token = result.csrf;
                if (response.status === 422) {
                    this.errors = result.errors ?? {};
                    this.status = 'error';
                    return;
                }
                if (!response.ok || result.status !== 'saved') {
                    this.status = 'error';
                    return;
                }
                this.state.profile.photo_path = result.url;
                this.photoMessage = result.message;
                this.status = 'saved';
                form.reset();
            } catch {
                this.status = 'error';
            } finally {
                this.busy = false;
                if (this.dirty) this.save();
            }
        },
    }));
});
