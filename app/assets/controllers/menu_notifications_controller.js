import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['badge'];
    static values = { messages: Number, patchNote: Boolean, admin: Number, membership: Number, latestNotice: Number, journal: Boolean, unreadJournal: Boolean };

    connect() {
        this.onMessages = event => { this.messagesValue = Number(event.detail.count) || 0; this.render(); };
        this.onAdmin = event => { this.adminValue = Number(event.detail.count) || 0; this.render(); };
        this.onActivity = event => {
            this.latestNoticeValue = Math.max(this.latestNoticeValue, Number(event.detail?.notificationId) || 0);
            if (event.detail?.journal === true) this.unreadJournalValue = true;
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

    render() {
        const unread = this.messagesValue > 0 || this.patchNoteValue || this.adminValue > 0 || this.unreadJournalValue;
        for (const badge of this.badgeTargets) badge.hidden = !unread;
    }
}
