import { defineConfig } from '@playwright/test';
export default defineConfig({ testDir:'./tests/browser', timeout:60000, use:{baseURL:'http://genesis-reborn.localhost',headless:true,launchOptions:{args:['--host-resolver-rules=MAP genesis-reborn.localhost 127.0.0.1']}}, reporter:'list' });
