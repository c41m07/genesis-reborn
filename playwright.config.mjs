import { defineConfig } from '@playwright/test';
const baseURL=process.env.PLAYWRIGHT_BASE_URL ?? 'http://genesis-reborn.localhost';
const host=new URL(baseURL).hostname;
export default defineConfig({ testDir:'./tests/browser', timeout:60000, use:{baseURL,headless:true,launchOptions:{args:[`--host-resolver-rules=MAP ${host} 127.0.0.1`]}}, reporter:'list' });
