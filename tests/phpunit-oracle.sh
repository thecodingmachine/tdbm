#!/usr/bin/env bash

# Use this file to start a Oracle database using Docker and then run the test suite on the Oracle database.

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd $DIR
cd ..

# TODO upgrade to php8.0
docker run --rm --name oracle-tdbm-test -v $(pwd):/app moufmouf/oracle-xe-php:7.4-11g vendor/bin/phpunit -c phpunit.oracle.xml

