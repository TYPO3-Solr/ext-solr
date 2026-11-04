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
	docker-compose exec ci /bin/bash -i

bootstrap:
	docker-compose exec ci /bin/bash -i "./Build/Test/bootstrap.sh"

test: up bootstrap
	docker-compose exec ci /bin/bash -i -c "composer tests:lint-php; composer t3:standards:fix -- --diff --dry-run; \
		composer tests:lint-xml; composer tests:phpstan; composer tests:unit; \
		typo3DatabaseName=\$$TYPO3_DATABASE_NAME typo3DatabaseHost=\$$TYPO3_DATABASE_HOST \
		typo3DatabaseUsername=\$$TYPO3_DATABASE_USERNAME typo3DatabasePassword=\$$TYPO3_DATABASE_PASSWORD \
		composer tests:integration"

down:
	docker-compose down

docs:
	docker-compose run --rm t3docmake

#############################
# Argument fix workaround
#############################
%:
	@:
