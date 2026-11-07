import { test, expect } from './support/isolated-test.mjs';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

test('Alertes : arrêt réel du worker isolé, signal récent et simulation sans mail', async () => {
  const project=process.env.BROWSER_ROUTER;
  if(!/^genesis-browser-test-[0-9]+-[0-9]+$/.test(project)) throw new Error('Projet de test isolé requis.');
  const compose=['compose','-p',project,'--env-file','.env.local','-f','compose.yaml','-f','compose.browser-test.yaml'];
  const persistentMounts = () => ['database', 'rabbitmq'].map(service => {
    const id = execFileSync('docker', [...compose, 'ps', '-q', service], { encoding: 'utf8' }).trim();
    const mounts = JSON.parse(execFileSync('docker', ['inspect', '--format', '{{json .Mounts}}', id], { encoding: 'utf8' }));
    const destination = service === 'database' ? '/var/lib/mysql' : '/var/lib/rabbitmq';
    const mount = mounts.find(value => value.Destination === destination);
    return { service, type: mount?.Type, name: mount?.Name };
  });
  const mountsBefore = process.env.BROWSER_PERSISTENCE === '1' ? persistentMounts() : null;
  if (mountsBefore) expect(mountsBefore.map(mount => mount.type)).toEqual(['volume', 'volume']);
  const check=()=>{
    const result=spawnSync('bash',['scripts/operations-check.sh','--send'],{encoding:'utf8',env:{...process.env,COMPOSE_PROJECT_NAME:project,OPS_ENV_FILE:'.env.local',OPS_COMPOSE_FILE:'compose.yaml',OPS_ALERT_ENABLED:'0'}});
    if(![0,1].includes(result.status)) throw new Error(result.stderr+result.stdout);
    return JSON.parse(result.stdout);
  };
  // Les tmpfs des conteneurs de test sont séparés : préparer un signal récent côté collecteur.
  execFileSync('docker',[...compose,'exec','-T','app','php','-r',"require 'vendor/autoload.php'; $heartbeat=new App\\Service\\ProcessHeartbeat('/app/var/run'); foreach(['worker','relay','notifier'] as $name) $heartbeat->touch($name);"],{stdio:'pipe'});
  const first=check();
  expect(first.delivery).toEqual({mode:'simulation',sent:0});
  expect(first.conditions.worker.status).toBe('ok');
  try {
    execFileSync('docker',[...compose,'stop','worker'],{stdio:'pipe'});
    const stopped=check();
    expect(stopped.conditions.worker.status).toBe('alert');
    expect(stopped.conditions.worker.grace).toBe(0);
    expect(stopped.conditions.worker.message).toContain('supervision : exited');
    expect(stopped.delivery).toEqual({mode:'simulation',sent:0});
  } finally { execFileSync('docker',[...compose,'up','-d','--wait','worker'],{stdio:'pipe'}); }
  if (mountsBefore) expect(persistentMounts()).toEqual(mountsBefore);
  await expect.poll(()=>check().conditions.worker.status).toBe('ok');
});


test('Sauvegarde : échec relayé au contrôle des alertes sans mail réel', async () => {
  const project=process.env.BROWSER_ROUTER;
  if(!/^genesis-browser-test-[0-9]+-[0-9]+$/.test(project)) throw new Error('Projet isolé requis.');
  const directory=mkdtempSync(join(tmpdir(),'genesis-backup-alert-test-'));
  try {
    execFileSync('python3',['scripts/backup-tools.py','record','--directory',directory,'--project',project,'--status','failed']);
    const result=spawnSync('bash',['scripts/operations-check.sh','--send'],{encoding:'utf8',env:{...process.env,COMPOSE_PROJECT_NAME:project,OPS_ENV_FILE:'.env.local',OPS_COMPOSE_FILE:'compose.yaml',GENESIS_PROJECT:project,BACKUP_DIR:directory}});
    expect(result.status).toBe(1);
    const snapshot=JSON.parse(result.stdout);
    expect(snapshot.delivery).toEqual({mode:'simulation',sent:0});
    expect(snapshot.conditions.backup.status).toBe('alert');
  } finally { rmSync(directory,{recursive:true,force:true}); }
});
