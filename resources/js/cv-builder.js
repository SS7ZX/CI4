document.addEventListener('alpine:init', () => {
    Alpine.data('cvBuilder', (config) => ({
        state: JSON.parse(document.getElementById('cv-builder-state').textContent),
        step: config.step,
        saveUrl: config.saveUrl,
        editUrl: config.editUrl,
        photoUrl: config.photoUrl,
        photoView: config.photoView,
        sectionLabels: config.sectionLabels,
        tokenName: config.tokenName,
        token: config.token,
        status: '',
        errors: {},
        photoMessage: '',
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
            this.state.sections = this.state.sections.length
                ? this.state.sections
                : ['profile', 'summary', 'experience', 'education', 'skills', 'links', 'projects', 'certifications', 'organizations', 'awards', 'languages', 'custom_sections']
                    .map((section_key, sort_order) => ({ section_key, is_visible: true, sort_order }));
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
