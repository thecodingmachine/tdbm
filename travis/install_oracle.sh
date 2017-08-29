#!/usr/bin/env bash

set -ev

wget 'https://github.com/cbandy/travis-oracle/archive/v2.0.2.tar.gz'
mkdir -p .travis/oracle
tar x -C .travis/oracle --strip-components=1 -f v2.0.2.tar.gz
.travis/oracle/download.sh
.travis/oracle/install.sh

"$ORACLE_HOME/bin/sqlplus" -L -S / AS SYSDBA <<SQL
create user tdbm_admin identified by tdbm_admin quota unlimited on USERS default tablespace USERS;
GRANT CONNECT,RESOURCE TO tdbm_admin;
GRANT dba TO tdbm_admin WITH ADMIN OPTION;
grant create session, create procedure, create type, create table, create sequence, create view to tdbm_admin;
grant select any dictionary to tdbm_admin;

exit
SQL


# Now, let's install the PHP driver
pear download pecl/oci8-2.1.4
tar xvzf oci8-2.1.4.tgz
cd oci8-2.1.4/
phpize
# export PHP_DTRACE=yes
./configure --with-oci8=shared,$ORACLE_HOME
make
make install
cd ..
phpenv config-add travis/oci8.ini
phpenv rehash
