.PHONY: app test-filter

up:
	docker-compose -f docker/docker-compose.yml --env-file ./.env -p php-test-task-olx-check-prices up -d --remove-orphans

down:
	docker-compose -f docker/docker-compose.yml --env-file ./.env -p php-test-task-olx-check-prices down

down-purge:
	docker-compose -f docker/docker-compose.yml --env-file ./.env -p php-test-task-olx-check-prices down -v --remove-orphans

build:
	docker-compose -f docker/docker-compose.yml --env-file ./.env -p php-test-task-olx-check-prices build --no-cache

logs:
	docker-compose -f docker/docker-compose.yml --env-file ./.env -p php-test-task-olx-check-prices logs -f

ps:
	docker-compose -f docker/docker-compose.yml --env-file ./.env -p php-test-task-olx-check-prices ps

restart:
	docker compose -f docker/docker-compose.yml --env-file ./.env -p php-test-task-olx-check-prices up -d

refresh: down build up logs

refresh-purge: down-purge remove-network build up logs

frontend-dev:
	pnpm install && pnpm run build && pnpm run dev

remove-network:
	- docker network rm php-test-task-olx-check-prices_network

migrate-seed:
	docker-compose -f docker/docker-compose.yml --env-file ./.env -p php-test-task-olx-check-prices exec app php artisan migrate --seed

migrate:
	docker-compose -f docker/docker-compose.yml --env-file ./.env -p php-test-task-olx-check-prices exec app php artisan migrate

# run example:
# make seed-class SEEDER=UserSeeder
seed-class:
	docker-compose -f docker/docker-compose.yml --env-file ./.env -p php-test-task-olx-check-prices exec app php artisan db:seed --class=$(SEEDER)

app-bash:
	docker-compose -f docker/docker-compose.yml --env-file ./.env -p php-test-task-olx-check-prices exec app bash

test:
	docker-compose -f docker/docker-compose.yml --env-file ./.env -p php-test-task-olx-check-prices exec app php artisan test

test-filter:
	docker-compose -f docker/docker-compose.yml --env-file ./.env -p php-test-task-olx-check-prices exec app php artisan test --filter=$(filter-out $@,$(MAKECMDGOALS))

build-webserver:
	docker compose -f docker/docker-compose.yml --env-file ./.env -p php-test-task-olx-check-prices up -d --build webserver

%:
	@:


