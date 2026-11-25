import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['badge'];
    static values = { messages: Number, patchNote: Boolean, admin: Number, membership: Number, latestNotice: Number, journal: Boolean, unreadJournal: Boolean, guildRecruitment: Number };

    connect() {
        this.onMessages = event => { this.messagesValue = Number(event.detail.count) || 0; this.render(); };
        this.onAdmin = event => { this.adminValue = Number(event.detail.count) || 0; this.render(); };
        this.onActivity = event => {
            this.latestNoticeValue = Math.max(this.latestNoticeValue, Number(event.detail?.notificationId) || 0);
        };
        this.onJournalState = ({ detail }) => {
            if (detail.membership !== this.membershipValue || typeof detail.unread !== 'boolean') return;
            this.unreadJournalValue = detail.unread;
            this.render();
        };
        this.onGuildRecruitment = ({ detail }) => {
            if (detail.membership !== this.membershipValue) return;
            this.guildRecruitmentValue = Number(detail.count) || 0;
            this.render();
        };
        window.addEventListener('genesis:guild-recruitment-count', this.onGuildRecruitment);
        window.addEventListener('genesis:journal-state', this.onJournalState);
        window.addEventListener('genesis:message-count', this.onMessages);
        window.addEventListener('genesis:admin-count', this.onAdmin);
        window.addEventListener('genesis:refresh', this.onActivity);
        this.render();
    }

    disconnect() {
        window.removeEventListener('genesis:guild-recruitment-count', this.onGuildRecruitment);
        window.removeEventListener('genesis:journal-state', this.onJournalState);
        window.removeEventListener('genesis:message-count', this.onMessages);
        window.removeEventListener('genesis:admin-count', this.onAdmin);
        window.removeEventListener('genesis:refresh', this.onActivity);
    }

    render() {
        const unread = this.messagesValue > 0 || this.patchNoteValue || this.adminValue > 0 || this.unreadJournalValue || this.guildRecruitmentValue > 0;
        for (const badge of this.badgeTargets) badge.hidden = !unread;
    }
}
