FROM thecodingmachine/php:7.4-v3-cli

USER root
RUN apt-get update && apt-get install wget

RUN mkdir /opt/oracle
RUN cd /opt/oracle && wget https://download.oracle.com/otn_software/linux/instantclient/19600/instantclient-basic-linux.x64-19.6.0.0.0dbru.zip && wget https://download.oracle.com/otn_software/linux/instantclient/19600/instantclient-sdk-linux.x64-19.6.0.0.0dbru.zip
RUN cd /tmp && wget https://pecl.php.net/get/oci8-2.2.0.tgz

RUN cd /opt/oracle && unzip instantclient-basic-linux.x64-19.6.0.0.0dbru.zip && unzip instantclient-sdk-linux.x64-19.6.0.0.0dbru.zip && echo /opt/oracle/instantclient_19_6 > /etc/ld.so.conf.d/oracle-instantclient.conf
RUN ldconfig

RUN apt-get install -y php-dev php-pear build-essential libaio1

RUN cd /tmp && tar zxf oci8-2.2.0.tgz && cd oci8-2.2.0 && phpize && ./configure --with-oci8=instantclient,/opt/oracle/instantclient_19_6 && make && make install

RUN echo extension=oci8.so > /etc/php/7.4/mods-available/oci8.ini
RUN cd /etc/php/7.4/cli/conf.d/ && ln -s /etc/php/7.4/mods-available/oci8.ini
RUN rm -rf /tmp/oci8-2.2.0 /tmp/oci8-2.2.0.tgz /opt/oracle/instantclient-basic-linux.x64-19.6.0.0.0dbru.zip /opt/oracle/instantclient-sdk-linuxx64.zip

ENV PHP_EXTENSIONS_XDEBUG=1

USER docker
