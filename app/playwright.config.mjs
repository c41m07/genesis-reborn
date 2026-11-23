import { defineConfig } from '@playwright/test';

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://genesis-reborn.localhost';
const host = new URL(baseURL).hostname;
const audits = /\/(?:ui-|.*(?:responsive|accessibility|keyboard|validation-gate|layout)|wireframe-theme|runtime-images|live-refresh-focus|navigation-final|entry-maquettes|dark-dashboard|common-shell)[^/]*\.spec\.mjs$/;
const administration = /\/(?:admin-|moderation-flow)[^/]*\.spec\.mjs$/;
const interactions = /\/(?:fleet-|guild-|galaxy-|pirate-|report-|conquest-|diplomacy-|global-chat|espionage-|message-|orbit-|personal-rivalries|social-|leaderboard-|public-profiles|strategic-tools)[^/]*\.spec\.mjs$/;

// Les audits transversaux ont priorité (y compris dans l’administration).
// Les autres parcours, dont les nouveaux fichiers, restent couverts par Empire.
export default defineConfig({
    testDir: './tests/browser',
    timeout: 60000,
    projects: [
        { name: 'audits', testMatch: audits },
        { name: 'administration', testMatch: administration, testIgnore: audits },
        { name: 'interactions', testMatch: interactions, testIgnore: [audits, administration] },
        { name: 'empire', testIgnore: [audits, administration, interactions] },
    ],
    use: { baseURL, headless: true, launchOptions: { args: [`--host-resolver-rules=MAP ${host} 127.0.0.1`] } },
    reporter: 'list',
});
