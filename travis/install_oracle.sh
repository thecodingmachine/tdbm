#!/usr/bin/env bash

set -ev

wget 'https://github.com/cbandy/travis-oracle/archive/v2.0.2.tar.gz'
mkdir -p .travis/oracle
tar x -C .travis/oracle --strip-components=1 -f v2.0.2.tar.gz
.travis/oracle/download.sh
.travis/oracle/install.sh

"$ORACLE_HOME/bin/sqlplus" -L -S / AS SYSDBA <<SQL
create user "tdbm" identified by "tdbm" quota unlimited on USERS default tablespace USERS;
grant create session, create procedure, create type, create table, create sequence, create view to "tdbm";
grant select any dictionary to "tdbm";

exit
SQL
