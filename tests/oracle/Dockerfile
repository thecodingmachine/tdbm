#FROM sath89/oracle-xe-11g
FROM wnameless/oracle-xe-11g

RUN apt-get update
#RUN apt-get install -y wget apt-transport-https lsb-release ca-certificates
#RUN wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg
#RUN echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
#RUN apt-get update
RUN apt-get install -y software-properties-common
RUN LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/php
RUN apt-get update
RUN apt-get install -y --force-yes --no-install-recommends php7.1 php7.1-dev build-essential php-pear php7.1-mysql php7.1-curl php7.1-json php7.1-gd php7.1-mcrypt php7.1-msgpack php7.1-memcached php7.1-intl php7.1-sqlite3 php7.1-gmp php7.1-geoip php7.1-mbstring php7.1-redis php7.1-xml php7.1-zip

# Now, let's install the PHP driver
RUN pear download pecl/oci8-2.1.4
RUN tar xvzf oci8-2.1.4.tgz
## RUN export PHP_DTRACE=yes
RUN cd oci8-2.1.4/ && phpize && ./configure --with-oci8=shared,/u01/app/oracle/product/11.2.0/xe && make && make install


ADD oci8.ini /etc/php/7.1/cli/conf.d/oci8.ini

RUN mkdir /app

ADD entrypoint_overloaded.sh /entrypoint_overloaded.sh
ENTRYPOINT ["/bin/bash", "-c", "/entrypoint_overloaded.sh ${*}", "--"]

