.DEFAULT_GOAL := help
DC = docker compose --env-file .env.local
EXEC = $(DC) exec -T --user $(shell id -u):$(shell id -g) app
.PHONY: help configure doctor validate build start stop down status logs shell console install migrate import seed-demo test lint assets watch verify-install test-browser backup restore ops-check backup-status test-backup
help: ## Afficher les commandes
	@awk 'BEGIN {FS = ":.*## "} /^[a-z-]+:.*## / {printf "%-16s %s\n", $$1, $$2}' $(MAKEFILE_LIST)
configure: ## Générer les secrets locaux sans écraser l'existant
	python3 scripts/configure.py
doctor: ## Vérifier Docker et les réseaux partagés
	docker compose version
	docker network inspect traefik_network mail_network --format '{{.Name}}'
validate: ## Valider Compose sans afficher les secrets
	$(DC) config --quiet
build: ## Construire PHP Apache
	$(DC) build
start: doctor ## Démarrer sans migration ni import
	$(DC) up -d --wait
stop: ## Arrêter ce projet, conserver les données
	$(DC) stop
down: ## Retirer les conteneurs du projet, conserver les volumes
	$(DC) down
status: ## État du projet
	$(DC) ps
logs: ## Suivre les journaux
	$(DC) logs --tail=50 -f
shell: ## Shell PHP
	$(DC) exec --user $(shell id -u):$(shell id -g) app bash
console: ## Console Symfony, ex. make console CMD=about
	$(EXEC) php bin/console $(CMD)
install: ## Installer les dépendances PHP et frontend verrouillées
	$(DC) run --rm --no-deps --user $(shell id -u):$(shell id -g) app composer install --no-interaction
	cd app && npm ci
assets: ## Compiler les assets Encore
	cd app && npm run build
watch: ## Recompiler les assets à chaque modification (actualiser le navigateur)
	cd app && npm run watch
migrate: ## Appliquer les migrations sur la nouvelle base
	$(EXEC) php bin/console doctrine:migrations:migrate --no-interaction
import: ## Importer les cartes et univers de référence
	$(EXEC) php bin/console app:universe:import
seed-demo: ## Créer le compte de démonstration local
	$(EXEC) php bin/console app:seed-demo
test: ## Tests unitaires, HTTP et MariaDB isolée en mémoire
	$(DC) --profile test up -d --wait database-test
	$(DC) exec -T --user root app chown -R $(shell id -u):$(shell id -g) var/cache
	$(EXEC) php bin/phpunit
	python3 -m unittest discover -s app/tests/scripts -p "test_backup*.py"
lint: ## Vérifier PHP, Twig, YAML, conteneur et schéma
	$(EXEC) sh -c 'find src tests migrations -name "*.php" -print0 | xargs -0 -n1 php -l >/dev/null'
	$(EXEC) php bin/console lint:yaml config
	$(EXEC) php bin/console lint:twig templates
	$(EXEC) php bin/console lint:container
	$(EXEC) php bin/console doctrine:schema:validate

test-browser: ## Vérifier les parcours navigateur dans une base MariaDB éphémère isolée
	bash scripts/test-browser-isolated.sh $(BROWSER_ARGS)
verify-install: ## Vérifier une installation vierge isolée et la persistance après redémarrage
	bash scripts/verify-install.sh
backup: ## Sauvegarder la base active en archive gzip avec checksum
	bash scripts/db-backup.sh
restore: ## Restaurer BACKUP_FILE après confirmation RESTORE_CONFIRM=<projet Compose>
	bash scripts/db-restore.sh

ops-check: ## Contrôler les alertes depuis l’hôte (simulation ; OPS_ARGS=--send pour envoyer si activé)
	bash scripts/operations-check.sh $(OPS_ARGS)

backup-status: ## Détecter échec ou succès de sauvegarde vieux de plus de 36 heures
	python3 scripts/backup-tools.py check
test-backup: ## Vérifier sauvegarde et restauration sur deux instances jetables isolées
	python3 scripts/test-backup-restore.py
