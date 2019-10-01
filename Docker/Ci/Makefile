ARGS = $(filter-out $@,$(MAKECMDGOALS))
MAKEFLAGS += --silent

list:
	sh -c "echo; $(MAKE) -p no_targets__ | awk -F':' '/^[a-zA-Z0-9][^\$$#\/\\t=]*:([^=]|$$)/ {split(\$$1,A,/ /);for(i in A)print A[i]}' | grep -v '__\$$' | grep -v 'Makefile'| sort"

#############################
# CI Testing
#############################
up:
	docker-compose up -d --build

bash:
	docker-compose exec --user travis ci /bin/bash -i

bootstrap:
	docker-compose exec --user travis ci /bin/bash -i "./Build/Test/bootstrap.sh"

test: up bootstrap
	docker-compose exec --user travis ci /bin/bash -i "./Build/Test/cibuild.sh"

down:
	docker-compose down

docs:
	docker-compose run --rm t3docmake

#############################
# Argument fix workaround
#############################
%:
	@: