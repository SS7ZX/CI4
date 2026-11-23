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
        paymentPaid: config.paymentPaid,
        themes: config.themes,
        sectionLabels: config.sectionLabels,
        messages: config.messages,
        tokenName: config.tokenName,
        token: config.token,
        status: '',
        savedAt: '',
        errors: {},
        photoMessage: '',
        selectedTheme: '',
        previewOpen: false,
        previewTrigger: null,
        previewThemeKey: '',
        previewFrameUrl: '',
        previewZoom: 1,
        previewUpdating: true,
        previewSettled: false,
        previewPreviousSections: new Map(),
        pageCountMessage: '',
        exportOpen: false,
        exportBusy: false,
        exportStep: 0,
        exportStatus: '',
        exportTrigger: null,
        busy: false,
        dirty: false,
        timer: null,

        init() {
            window.addEventListener('beforeunload', (event) => {
                if (!this.dirty) return;
                event.preventDefault();
                event.returnValue = '';
            });
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
                this.refreshPreviewFrame();
                this.refreshPageCount();
            }
            if (this.step === 6) {
                this.$nextTick(() => {
                    import('/assets/js/section-reorder.js')
                        .then(({ initializeSectionReorder }) => {
                            initializeSectionReorder(this.$root.querySelector('[data-section-sort-list]'));
                        })
                        .catch(() => {
                            this.status = 'error';
                            this.errors.sections = this.messages.reorderError;
                        });
                });
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
                        this.dirty = true;
                        this.errors = result.errors ?? {};
                        this.status = 'error';
                        return false;
                    }
                    if (!response.ok || result.status !== 'saved') {
                        this.dirty = true;
                        this.status = 'error';
                        return false;
                    }
                } while (this.dirty);

                this.status = 'saved';
                this.savedAt = new Intl.DateTimeFormat('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                }).format(new Date());
                if (this.step === 8) {
                    this.refreshPreviewFrame();
                    await this.refreshPageCount();
                }
                return true;
            } catch {
                this.dirty = true;
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

        statusLabel() {
            if (this.status === 'saving') return this.messages.saving;
            if (this.status === 'saved') {
                return this.messages.savedAt.replace('{0}', this.savedAt);
            }
            if (this.status === 'error') return this.messages.saveError;
            return this.messages.reviewStatus;
        },

        async downloadPdf() {
            if (this.busy) return;
            this.exportTrigger = document.activeElement;
            this.exportOpen = true;
            this.exportStep = 1;
            this.exportStatus = this.messages.exportSave;
            this.$nextTick(() => this.$refs.exportClose?.focus());
            if (!await this.save()) {
                this.exportStep = 0;
                this.exportStatus = this.messages.exportError;
                return false;
            }
            if (!this.paymentPaid) {
                window.location.assign(this.downloadUrl);
                return true;
            }

            this.exportBusy = true;
            this.exportStep = 2;
            this.exportStatus = this.messages.exportGenerate;
            try {
                const response = await fetch(this.downloadUrl, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/pdf' },
                });
                const contentType = response.headers.get('Content-Type') ?? '';
                if (!response.ok || !contentType.toLowerCase().includes('application/pdf')) {
                    window.location.assign(response.url || this.downloadUrl);
                    return false;
                }

                const blob = await response.blob();
                const disposition = response.headers.get('Content-Disposition') ?? '';
                const filenameMatch = disposition.match(/filename\*?=(?:UTF-8''|")?([^";]+)/i);
                const filename = filenameMatch
                    ? decodeURIComponent(filenameMatch[1].replace(/["']/g, ''))
                    : 'cv.pdf';
                const objectUrl = URL.createObjectURL(blob);
                const anchor = document.createElement('a');
                anchor.href = objectUrl;
                anchor.download = filename;
                anchor.click();
                window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
                this.exportStep = 3;
                this.exportStatus = this.messages.exportReady;
                window.cvToasts?.show(this.messages.exportSuccess);
                return true;
            } catch {
                this.exportStep = 0;
                this.exportStatus = this.messages.exportError;
                return false;
            } finally {
                this.exportBusy = false;
            }
        },

        previewTheme(themeKey) {
            this.previewThemeKey = themeKey;
            this.previewUpdating = true;
            this.previewFrameUrl = this.themePreviewUrl.replace('__theme__', encodeURIComponent(themeKey));
            this.previewTrigger = document.activeElement;
            this.previewOpen = true;
            this.$nextTick(() => this.$refs.previewClose?.focus());
        },

        previewThemeName() {
            return this.themes.find((theme) => theme.key === this.previewThemeKey)?.name ?? '';
        },

        previewThemeIsAtsFriendly() {
            return this.themes.find((theme) => theme.key === this.previewThemeKey)?.ats_friendly === true;
        },

        applyPreviewTheme() {
            if (this.previewThemeKey === '') return;
            this.selectedTheme = this.previewThemeKey;
            this.scheduleSave();
            this.closePreview();
        },

        closePreview() {
            this.previewOpen = false;
            this.$nextTick(() => this.previewTrigger?.focus());
        },

        closeExport() {
            if (this.exportBusy) return;
            this.exportOpen = false;
            this.$nextTick(() => this.exportTrigger?.focus());
        },

        trapExportFocus(event) {
            if (!this.exportOpen || event.key !== 'Tab') return;
            const focusable = [...this.$refs.exportDialog.querySelectorAll('button:not([disabled])')];
            if (focusable.length === 0) {
                event.preventDefault();
                this.$refs.exportDialog.focus();
                return;
            }
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

        refreshPreviewFrame() {
            const frame = this.$refs.previewFrame;
            const previewDocument = frame?.contentDocument;
            if (previewDocument) {
                this.previewPreviousSections = new Map(
                    [...previewDocument.querySelectorAll('.cv-section')].map((section) => [
                        section.className,
                        section.textContent,
                    ]),
                );
            }
            this.previewUpdating = true;
            this.previewSettled = false;
            this.previewFrameUrl = `${this.previewUrl}?v=${Date.now()}`;
        },

        previewLoaded() {
            this.previewUpdating = false;
            this.previewSettled = true;
            const frame = this.$refs.previewFrame;
            const previewDocument = frame?.contentDocument;
            if (!previewDocument) return;
            for (const section of previewDocument.querySelectorAll('.cv-section')) {
                if (this.previewPreviousSections.get(section.className) === section.textContent) {
                    continue;
                }
                section.classList.add('cv-change-highlight');
                section.addEventListener('animationend', () => section.classList.remove('cv-change-highlight'), { once: true });
            }
            this.previewPreviousSections.clear();
        },

        zoomPreview(amount) {
            this.previewZoom = Math.min(1.25, Math.max(0.75, Math.round((this.previewZoom + amount) * 100) / 100));
        },

        fitPreview() {
            const frame = this.$refs.previewFrame;
            const documentWidth = frame?.contentDocument?.documentElement?.scrollWidth ?? 0;
            const containerWidth = frame?.parentElement?.clientWidth ?? 0;
            this.previewZoom = documentWidth > 0 && containerWidth > 0
                ? Math.min(1, Math.max(0.75, containerWidth / documentWidth))
                : 1;
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
            this.previewUpdating = false;
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

        reorderSections(keys) {
            const sections = new Map(this.state.sections.map((section) => [section.section_key, section]));
            if (keys.length !== sections.size || keys.some((key) => !sections.has(key))) {
                this.errors.sections = this.messages.reorderError;
                return;
            }

            this.state.sections = keys.map((section_key, sort_order) => ({
                ...sections.get(section_key),
                sort_order,
            }));
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
                this.savedAt = new Intl.DateTimeFormat('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                }).format(new Date());
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
