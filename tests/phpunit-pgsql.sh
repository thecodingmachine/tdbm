#!/usr/bin/env bash

# Use this file to start a PostgreSQL database using Docker and then run the test suite on the PostgreSQL database.

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd $DIR
cd ..

docker run --rm --name postgres-tdbm-test -p 5432:5432 -e POSTGRES_PASSWORD= -d postgres:9.6

vendor/bin/phpunit -c phpunit.postgres.xml

docker stop postgres-tdbm-test
