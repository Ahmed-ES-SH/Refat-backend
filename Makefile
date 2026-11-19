.PHONY: up start stop down destroy build restart logs ps sh-backend sh-frontend migrate fresh seed artisan mysql

up:
	docker compose up -d --build

start:
	docker compose up -d

stop:
	docker compose stop

down:
	docker compose down

destroy:
	docker compose down -v

build:
	docker compose build

restart:
	docker compose restart

logs:
	docker compose logs -f

ps:
	docker compose ps

sh-backend:
	docker compose exec backend sh

sh-frontend:
	docker compose exec frontend sh

migrate:
	docker compose exec backend php artisan migrate

fresh:
	docker compose exec backend php artisan migrate:fresh

seed:
	docker compose exec backend php artisan db:seed

artisan:
	docker compose exec backend php artisan $(ARGS)

mysql:
	docker compose exec mysql sh -c 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'
