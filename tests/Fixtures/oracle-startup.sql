create user tdbm_admin identified by tdbm_admin quota unlimited on USERS default tablespace USERS;
GRANT CONNECT,RESOURCE TO tdbm_admin;
GRANT dba TO tdbm_admin WITH ADMIN OPTION;
grant create session, create procedure, create type, create table, create sequence, create view to tdbm_admin;
grant select any dictionary to tdbm_admin;


-- create user tdbm identified by tdbm quota unlimited on USERS default tablespace USERS;
-- GRANT CONNECT,RESOURCE,DBA TO tdbm;
-- grant create session, create procedure, create type, create table, create sequence, create view to tdbm;
-- grant select any dictionary to tdbm;
