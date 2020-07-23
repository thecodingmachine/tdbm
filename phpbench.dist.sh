#!/bin/bash

export DB_DRIVER=pdo_mysql
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_USERNAME=root
#export DB_ADMIN_USERNAME=root
export DB_PASSWORD=
export DB_NAME=tdbm_benchmark

vendor/bin/phpbench "$@"
