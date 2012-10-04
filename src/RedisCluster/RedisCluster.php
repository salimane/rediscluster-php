<?php
/*
 * This file is part of the RedisCluster package.
 *
 * (c) Salimane Adjao Moustapha <me@salimane.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RedisCluster;

/**
 * Implementation of the RedisCluster Client using phpredis extension Redis class
 * This abstract class provides a php interface to all Redis commands on the cluster of redis servers.
 * and implementing how the commands are sent to and received from the cluster.
 *
 * @author Salimane Adjao Moustapha <me@salimane.com>
 */
class RedisCluster {

  /**
   * servers array used during construct
   * @var array
   * @access public
   */
  public $cluster;

  /**
   * number of servers array used during construct
   * @var array
   * @access public
   */
  public $no_servers;

  /**
   * Collection of Redis objects attached to Redis servers
   * @var array
   * @access private
   */
  private $redises;

  /**
   * instance of the Redis class from php extension
   * @var resource
   * @access private
   */
  private $__redis;


  /**
   * Creates a Redis interface to a cluster of Redis servers
   * @param array $cluster The Redis servers in the cluster.
   */
  function __construct($cluster, $redisdb = 0) {

  }

  /**
   * Magic method to handle all function requests
   *
   * @param string $name The name of the method called.
   * @param array $args Array of supplied arguments to the method.
   * @return mixed Return value from Redis::__call() based on the command.
   */
  function __call($name, $args){
    return 'test';
  }

}
