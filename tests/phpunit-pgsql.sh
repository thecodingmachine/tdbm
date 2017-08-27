#!/usr/bin/env bash

# Use this file to start a PostgreSQL database using Docker and then run the test suite on the PostgreSQL database.

docker run --name postgres-tdbm-test -p 5432:5432 -e POSTGRES_PASSWORD= -d postgres:9.6

vendor/bin/phpunit -c phpunit.postgres.xml

docker stop postgres-tdbm-test
docker rm postgres-tdbm-test
