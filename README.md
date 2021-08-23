[![Latest Stable Version](https://poser.pugx.org/thecodingmachine/tdbm/v/stable)](https://packagist.org/packages/thecodingmachine/tdbm)
[![Total Downloads](https://poser.pugx.org/thecodingmachine/tdbm/downloads)](https://packagist.org/packages/thecodingmachine/tdbm)
[![Latest Unstable Version](https://poser.pugx.org/thecodingmachine/tdbm/v/unstable)](https://packagist.org/packages/thecodingmachine/tdbm)
[![License](https://poser.pugx.org/thecodingmachine/tdbm/license)](https://packagist.org/packages/thecodingmachine/tdbm)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/thecodingmachine/tdbm/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/thecodingmachine/tdbm/?branch=master)
[![Build Status](https://travis-ci.org/thecodingmachine/tdbm.svg?branch=master)](https://travis-ci.org/thecodingmachine/tdbm)
[![Coverage Status](https://coveralls.io/repos/thecodingmachine/tdbm/badge.svg?branch=master&service=github)](https://coveralls.io/github/thecodingmachine/tdbm?branch=master)


TDBM (The DataBase Machine)
===========================

Check out [the documentation at https://thecodingmachine.github.io/tdbm/](https://thecodingmachine.github.io/tdbm/).

## Run the test locally

### Postgres

Run an instance with `docker run -p 5432:5432 -e POSTGRES_HOST_AUTH_METHOD=trust postgres:12`

Run the tests with `vendor/bin/phpunit -c phpunit.postgres.xml`
