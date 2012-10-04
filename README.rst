rediscluster-php
===============

a PHP interface to a Cluster of Redis key-value stores.

Project Goals
-------------

The goal of ``rediscluster-php``, together with `rediscluster-py <https://github.com/salimane/rediscluster-py.git>`_, 
is to have a consistent, compatible client libraries accross programming languages
when sharding among different Redis instances in a transparent, fast, and 
fault tolerant way. ``rediscluster-php`` uses `phpredis <https://github.com/nicolasff/phpredis.git>`_
when connecting to the redis servers, thus the original api commands would work without problems within
the context of a cluster of redis servers.

Travis CI
---------

Currently, ``rediscluster-php`` is being tested via travis ci for php
version 5.3 and 5.4: |Build Status|

Installation
------------

Download via `Composer <http://getcomposer.org/>`_
Create a ``composer.json`` file if you don't already have one in your projects root directory and require rediscluster:

::

    {
      "require": {
        "rediscluster/rediscluster": "dev-master"
      }
    }

Install Composer:

::

    $ curl -s http://getcomposer.org/installer | php

Run the install command:

::

    $ php composer.phar install

This will download ``rediscluster`` into the `vendor/rediscluster/rediscluster` directory.
To learn more about Composer visit http://getcomposer.org/

Running Tests
-------------

::

    $ git clone https://github.com/salimane/rediscluster-php.git
    $ cd rediscluster-php
    $ vi Tests/config.php
    $ phpunit

Getting Started
---------------

::

    php -a
    Interactive shell
    
    php > require "/home/salimane/htdocs/rediscluster-php/vendor/autoload.php";
    php > $cluster = array(
    php (     //node names
    php (     'nodes' => array(
    php (       //masters
    php (       'node_1' => array('host' => '127.0.0.1', 'port' => 63791),
    php (       'node_2' => array('host' => '127.0.0.1', 'port' => 63792),
    php (       //slaves
    php (       'node_5' => array('host' => '127.0.0.1', 'port' => 63795),
    php (       'node_6' => array('host' => '127.0.0.1', 'port' => 63796),
    php (     ),
    php (     //replication information
    php (     'master_of' => array(
    php (       'node_1' => 'node_6',  //node_6 slaveof node_1 in redis6.conf
    php (       'node_2' => 'node_5',  // node_5 slaveof node_2 in redis5.conf
    php (     ),
    php ( 
    php (     'default_node' => 'node_1'
    php ( );
    php >
    php > $r = new RedisCluster\RedisCluster($cluster, 4);
    php > var_dump($r->set('foo', 'bar'));
    bool(true)
    php > var_dump($r->get('foo'));
    string(3) "bar"


Cluster Configuration
---------------------

The cluster configuration is a hash that is mostly based on the idea of a node, which is simply a host:port pair
that points to a single redis-server instance. This is to make sure it doesn’t get tied it
to a specific host (or port).
The advantage of this is that it is easy to add or remove nodes from 
the system to adjust the capacity while the system is running.

Read Slaves & Write Masters
---------------------------

``rediscluster`` uses master/slave mappings stored in the cluster hash passed during instantiation to 
transparently relay read redis commands to slaves and writes commands to masters.

Partitioning Algorithm
----------------------

In order to map every given key to the appropriate Redis node, the algorithm used, based on crc32 and modulo, is :

::
    
    ((abs(crc32(<key>)) % <number of masters>) + 1)


A function ``getnodefor`` is provided to get the node a particular key will be/has been stored to.

::

    php > print_r($r->getnodefor('foo'));
    Array
    (
        [node_2] => Array
            (
                [host] => 127.0.0.1
                [port] => 63792
            )
    
    )
    php >     

Hash Tags
-----------

In order to specify your own hash key (so that related keys can all land 
on a given node), ``rediscluster`` allows you to pass a list where you’d normally pass a scalar.
The first element of the list is the key to use for the hash and the 
second is the real key that should be fetched/modify:

::

    php > $r->get("bar{foo}")

In that case “foo” is the hash key but “bar” is still the name of
the key that is fetched from the redis node that “foo” hashes to.

Multiple Keys Redis Commands
----------------------------

In the context of storing an application data accross many redis servers, commands taking multiple keys 
as arguments are harder to use since, if the two keys will hash to two different 
instances, the operation can not be performed. Fortunately, rediscluster is a little fault tolerant 
in that it still fetches the right result for those multi keys operations as far as the client is concerned.
To do so it processes the related involved redis servers at interface level.

::

    php > foreach(array('b1', 'a2', 'b3') as $i) $r->sadd('bar', $i);
    php > foreach(array('a1', 'a2', 'a3') as $i) $r->sadd('foo', $i);
    php > var_dump($r->sdiffstore('foobar', 'foo', 'bar'));
    int(2)
    php >
    php > print_r($r->smembers('foobar'));
    Array
    (
        [0] => a1
        [1] => a3
    )
    php > 
    php > print_r($r->getnodefor('foo'));
    Array
    (
        [node_2] => Array
            (
                [host] => 127.0.0.1
                [port] => 63792
            )
    
    )
    php > print_r($r->getnodefor('bar'));
    Array
    (
        [node_1] => Array
            (
                [host] => 127.0.0.1
                [port] => 63791
            )
    
    )
    php > print_r($r->getnodefor('foobar'));
    Array
    (
        [node_2] => Array
            (
                [host] => 127.0.0.1
                [port] => 63792
            )
    
    )
    php > 


Redis-Sharding & Redis-Copy
---------------------------

In order to help with moving an application with a single redis server to a cluster of redis servers
that could take advantage of ``rediscluster``, i wrote `redis-sharding <https://github.com/salimane/redis-tools#redis-sharding>`_ 
and `redis-copy <https://github.com/salimane/redis-tools#redis-copy>`_

Information
-----------

-  Code: ``git clone git://github.com/salimane/rediscluster-php.git``
-  Home: http://github.com/salimane/rediscluster-php
-  Bugs: http://github.com/salimane/rediscluster-php/issues

Author
------

``rediscluster-php`` is developed and maintained by Salimane Adjao Moustapha
(me@salimane.com). It can be found here:
http://github.com/salimane/rediscluster-php

.. |Build Status| image:: https://secure.travis-ci.org/salimane/rediscluster-php.png?branch=master
   :target: http://travis-ci.org/salimane/rediscluster-php
