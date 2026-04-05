.PHONY: up down build shell migrate test sync console

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build --no-cache

shell:
	docker compose exec php bash

migrate:
	docker compose exec php bin/console doctrine:migrations:migrate --no-interaction

test:
	docker compose exec php bin/phpunit

sync:
	docker compose exec php bin/console app:sync

console:
	docker compose exec php bin/console $(cmd)
