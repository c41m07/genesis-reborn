import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['badge'];
    static values = { messages: Number, patchNote: Boolean, admin: Number, membership: Number, latestNotice: Number, journal: Boolean };

    connect() {
        this.lastSeenNotice = 0;
        try { this.lastSeenNotice = Number(window.localStorage.getItem(this.storageKey)) || 0; } catch { /* Le témoin reste utilisable sans stockage local. */ }
        if (this.journalValue) this.readJournal();
        this.onMessages = event => { this.messagesValue = Number(event.detail.count) || 0; this.render(); };
        this.onAdmin = event => { this.adminValue = Number(event.detail.count) || 0; this.render(); };
        this.onActivity = event => {
            this.latestNoticeValue = Math.max(this.latestNoticeValue, Number(event.detail?.notificationId) || 0);
            if (this.journalValue) this.readJournal();
            this.render();
        };
        window.addEventListener('genesis:message-count', this.onMessages);
        window.addEventListener('genesis:admin-count', this.onAdmin);
        window.addEventListener('genesis:refresh', this.onActivity);
        this.render();
    }

    disconnect() {
        window.removeEventListener('genesis:message-count', this.onMessages);
        window.removeEventListener('genesis:admin-count', this.onAdmin);
        window.removeEventListener('genesis:refresh', this.onActivity);
    }

    get storageKey() { return `genesis-journal-seen-${this.membershipValue}`; }

    readJournal() {
        this.lastSeenNotice = this.latestNoticeValue;
        if (this.membershipValue > 0) {
            try { window.localStorage.setItem(this.storageKey, String(this.lastSeenNotice)); } catch { /* La lecture courante suffit si le stockage est indisponible. */ }
        }
    }

    render() {
        const unread = this.messagesValue > 0 || this.patchNoteValue || this.adminValue > 0 || this.latestNoticeValue > this.lastSeenNotice;
        for (const badge of this.badgeTargets) badge.hidden = !unread;
    }
}
