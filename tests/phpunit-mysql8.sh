#!/usr/bin/env bash

# Use this file to start a PostgreSQL database using Docker and then run the test suite on the PostgreSQL database.

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd $DIR
cd ..

docker run --rm --name mysql8-tdbm-test -p 3306:3306 -p 33060:33060 -e MYSQL_ROOT_PASSWORD= -e MYSQL_ALLOW_EMPTY_PASSWORD=1 -d mysql:8 mysqld --default-authentication-plugin=mysql_native_password

# Let's wait for MySQL 8 to start
sleep 20

vendor/bin/phpunit -c phpunit.mysql8.xml
RESULT_CODE=$?

docker stop mysql8-tdbm-test

exit $RESULT_CODE
