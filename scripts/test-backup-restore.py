#!/usr/bin/env python3
"""Restaurer uniquement deux projets jetables créés par ce test ; aucun consommateur."""
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
import uuid

ROOT = Path(__file__).resolve().parents[1]


def run(args, *, data=None, env=None):
    result = subprocess.run(args, cwd=ROOT, input=data, text=True, capture_output=True, env=env)
    if result.returncode:
        raise RuntimeError("Commande de test en échec : " + " ".join(args[:4]) + "\n" + result.stdout + result.stderr)
    return result.stdout


def main():
    suffix = uuid.uuid4().hex
    source = "genesis-backup-source-" + suffix
    target = "genesis-restore-test-" + suffix
    with tempfile.TemporaryDirectory(prefix="genesis-restore-proof-") as temporary:
        temporary = Path(temporary)
        override = temporary / "isolated.yaml"
        override.write_text("""services:
  database:
    ports: !reset []
    volumes: !reset []
    tmpfs: [/var/lib/mysql]
    command: ["--event-scheduler=OFF"]
  app:
    environment:
      APP_ENV: dev
      APP_DEBUG: "0"
      MAILER_DSN: "null://null"
      OPS_ALERT_ENABLED: "0"
    networks: !override [backend]
    tmpfs: ["/app/var:mode=1777"]
""")

        def compose(project):
            if not re.fullmatch(r"genesis-(?:backup-source|restore-test)-[a-f0-9]{32}", project):
                raise RuntimeError("Projet non isolé refusé")
            return ["docker", "compose", "-p", project, "--env-file", ".env.local", "-f", "compose.yaml", "-f", str(override)]

        def sql(project, query):
            return run(compose(project) + ["exec", "-T", "database", "sh", "-ec", 'exec mariadb --user=root --password="$MARIADB_ROOT_PASSWORD" --batch --raw --skip-column-names "$MARIADB_DATABASE"'], data=query)

        def app(project, *arguments, data=None):
            return run(compose(project) + ["run", "--pull", "never", "--rm", "--no-deps", "-T", "--user", str(os.getuid())+":"+str(os.getgid()), "app", "php", *arguments], data=data)

        def fingerprint(project):
            tables = sql(project, "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;").splitlines()
            values = {}
            for table in tables:
                if not re.fullmatch(r"[a-z0-9_]+", table):
                    raise RuntimeError("Nom de table inattendu")
                values[table] = hashlib.sha256(sql(project, "SELECT * FROM `"+table+"` ORDER BY 1;").encode()).hexdigest()
            return values

        try:
            for project in [source, target]:
                run(compose(project) + ["up", "-d", "--pull", "never", "--wait", "database"])
                assert sql(project, "SELECT @@event_scheduler;").strip() == "OFF"
                assert run(compose(project) + ["ps", "--services", "--filter", "status=running"]).splitlines() == ["database"]
            app(source, "bin/console", "doctrine:migrations:migrate", "--no-interaction")
            app(source, "bin/console", "app:universe:import")
            app(source, "bin/console", "app:seed-demo")
            sql(source, """
UPDATE planet_resource SET current_stock=12345.5 WHERE resource_type='metal';
INSERT INTO planet_building (planet_id,slug,tier,level) SELECT id,'mine_metal','basic',3 FROM planet WHERE owner_id IS NOT NULL;
INSERT INTO player_technology (player_universe_id,slug,tier,level) SELECT id,'engineering_foundations','basic',2 FROM player_universe;
INSERT INTO fleet (player_universe_id,home_planet_id,status,mission,speed_ratio,cargo,reserved_fuel,orbit_fuel,orbit_duration_seconds,travel_seconds,dispatch_token)
SELECT owner_id,id,'idle','idle',1,'{}',0,0,0,0,'backup-fixture-fleet' FROM planet WHERE owner_id IS NOT NULL;
INSERT INTO fleet_ship (fleet_id,slug,tier,quantity) SELECT id,'fighter','basic',7 FROM fleet;
INSERT INTO queue_item (planet_id,player_universe_id,queue_type,item_slug,tier,item_level,quantity,duration_seconds,started_at,queued_at,dispatch_token)
SELECT id,owner_id,'building','mine_metal','basic',4,1,3600,UTC_TIMESTAMP(),UTC_TIMESTAMP(),'backup-fixture-queue' FROM planet WHERE owner_id IS NOT NULL;
INSERT INTO outbox_message (kind,target_id,token,due_at) SELECT 'queue',id,dispatch_token,DATE_ADD(started_at,INTERVAL duration_seconds SECOND) FROM queue_item;
""")
            expected = fingerprint(source)
            assert len(expected) > 10
            # Les scripts réels acceptent un fichier Compose unique ; générer leur configuration isolée.
            source_config = temporary / "source.json"
            target_config = temporary / "target.json"
            source_config.write_text(run(compose(source) + ["config", "--format", "json"]))
            target_config.write_text(run(compose(target) + ["config", "--format", "json"]))
            source_config.chmod(0o600)
            target_config.chmod(0o600)
            archives = temporary / "archives"
            env = dict(os.environ, GENESIS_PROJECT=source, GENESIS_COMPOSE_FILE=str(source_config), GENESIS_ENV_FILE=".env.local", BACKUP_DIR=str(archives))
            run(["bash", "scripts/db-backup.sh"], env=env)
            archive = next(archives.glob("*.sql.gz"))
            sql(target, "CREATE TABLE restore_marker (id INT PRIMARY KEY);")
            restore_env = dict(env, GENESIS_PROJECT=target, GENESIS_COMPOSE_FILE=str(target_config), RESTORE_CONFIRM=target, BACKUP_FILE=str(archive))
            run(["bash", "scripts/db-restore.sh"], env=restore_env)
            assert fingerprint(target) == expected, "Données restaurées différentes"
            assert float(sql(target, "SELECT current_stock FROM planet_resource WHERE resource_type='metal';").strip()) == 12345.5
            assert fingerprint(source) == expected, "Source modifiée par la restauration"
            assert sql(target, "SELECT level FROM planet_building WHERE slug='mine_metal';").strip() == "3"
            assert sql(target, "SELECT level FROM player_technology;").strip() == "2"
            assert sql(target, "SELECT quantity FROM fleet_ship;").strip() == "7"
            assert sql(target, "SELECT COUNT(*) FROM queue_item;").strip() == "1"
            app(target, data="""<?php
require '/app/vendor/autoload.php';
(new Symfony\\Component\\Dotenv\\Dotenv())->bootEnv('/app/.env');
$parser=new Doctrine\\DBAL\\Tools\\DsnParser(['mysql'=>'pdo_mysql']);
$db=Doctrine\\DBAL\\DriverManager::getConnection($parser->parse($_SERVER['DATABASE_URL']));
$player=$db->fetchAssociative("SELECT * FROM player WHERE email='explorateur@genesis.local'");
if (!$player || !$player['email_verified_at'] || !$player['current_player_universe_id'] || !password_verify('Genesis-Demo-2026!',$player['password'])) exit(2);
echo "Compte vérifié et mot de passe restaurés.\n";
""")
            for project in [source, target]:
                assert sql(project, "SELECT @@event_scheduler;").strip() == "OFF"
                assert run(compose(project) + ["ps", "--services", "--filter", "status=running"]).splitlines() == ["database"]
            print(json.dumps({"result": "ok", "tables_compared": len(expected), "account_password_verified": True, "progression_verified": ["resources", "buildings", "research", "ships", "pending_queue", "outbox"], "consumers_started": False, "real_mail_sent": False}, ensure_ascii=False))
        finally:
            cleanup_errors = []
            for project in [source, target]:
                result = subprocess.run(compose(project) + ["down", "--volumes", "--remove-orphans"], cwd=ROOT, capture_output=True)
                if result.returncode:
                    cleanup_errors.append(project)
            if cleanup_errors:
                raise RuntimeError("Nettoyage des projets jetables à vérifier : " + ", ".join(cleanup_errors))


if __name__ == "__main__":
    main()
