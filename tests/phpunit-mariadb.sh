#!/usr/bin/env bash

# Use this file to start a PostgreSQL database using Docker and then run the test suite on the PostgreSQL database.

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd $DIR
cd ..

docker run --name mariadb-tdbm-test -p 3306:3306 -e MYSQL_ROOT_PASSWORD=root -d mariadb:10.3

# Let's wait for MariaDB to start
sleep 20

vendor/bin/phpunit -c phpunit.mariadb.xml

docker stop mariadb-tdbm-test
docker rm mariadb-tdbm-test
