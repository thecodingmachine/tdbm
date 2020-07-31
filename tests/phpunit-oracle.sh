#!/usr/bin/env bash

# Use this file to start a PostgreSQL database using Docker and then run the test suite on the PostgreSQL database.

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd $DIR
cd ..

docker run --rm --name oracle-tdbm-test -v $(pwd):/app moufmouf/oracle-xe-php:7.4-11g vendor/bin/phpunit -c phpunit.oracle.xml

