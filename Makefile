.DEFAULT_GOAL := help
DC = docker compose --env-file .env.local
EXEC = $(DC) exec -T --user $(shell id -u):$(shell id -g) app
.PHONY: help configure doctor validate build start stop down status logs shell console install migrate import seed-demo test lint assets verify-install test-browser
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
	npm ci
assets: ## Compiler les assets Encore
	npm run build
migrate: ## Appliquer les migrations sur la nouvelle base
	$(EXEC) php bin/console doctrine:migrations:migrate --no-interaction
import: ## Importer les cartes et univers de référence
	$(EXEC) php bin/console app:universe:import
seed-demo: ## Créer le compte de démonstration local
	$(EXEC) php bin/console app:seed-demo
test: ## Tests unitaires, HTTP et MariaDB isolée en mémoire
	$(DC) --profile test up -d --wait database-test
	$(EXEC) php bin/phpunit
lint: ## Vérifier PHP, Twig, YAML, conteneur et schéma
	$(EXEC) sh -c 'find src tests migrations -name "*.php" -print0 | xargs -0 -n1 php -l >/dev/null'
	$(EXEC) php bin/console lint:yaml config
	$(EXEC) php bin/console lint:twig templates
	$(EXEC) php bin/console lint:container
	$(EXEC) php bin/console doctrine:schema:validate

test-browser: ## Vérifier les parcours navigateur locaux (création de comptes de test)
	npx playwright test
verify-install: ## Vérifier une installation vierge isolée et la persistance après redémarrage
	bash scripts/verify-install.sh
