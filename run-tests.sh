#!/bin/sh
# Runs PHPUnit inside Docker (no PHP on the host). Needs the postgres test container on network ml-net:
#   docker run -d --name ml-test-db --network ml-net -p 55432:5432 -e POSTGRES_USER=library -e POSTGRES_PASSWORD=library -e POSTGRES_DB=library_test postgres:16-alpine
MSYS_NO_PATHCONV=1 docker run --rm --entrypoint "" --network ml-net -v "$(pwd -W 2>/dev/null || pwd):/var/www" -w /var/www ml-api-dev "$@"
