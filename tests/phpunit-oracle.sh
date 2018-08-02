#!/usr/bin/env bash

# Use this file to start a PostgreSQL database using Docker and then run the test suite on the PostgreSQL database.

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd $DIR
cd ..

docker run --rm --name oracle-tdbm-test -v $(pwd):/app -v $(pwd)/tests/Fixtures/oracle-startup.sql:/docker-entrypoint-initdb.d/oracle-startup.sql moufmouf/oracle-xe-php vendor/bin/phpunit -c phpunit.oracle.xml

