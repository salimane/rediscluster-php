<?php
/**
 * config file of the RedisCluster test cases
 *
 * @category RedisCluster
 * @package  RedisCluster
 * @author   (c) Salimane Adjao Moustapha <me@salimane.com>
 * @license  MIT http://www.opensource.org/licenses/mit-license.php
 * @version  GIT:258f9e4
 * @link     https://github.com/salimane/rediscluster-php
 */

global $cluster;
$cluster = array(
    //node names
    'nodes' => array(
      //masters
      'node_1' => array('host' => '127.0.0.1', 'port' => 63791),
      'node_2' => array('host' => '127.0.0.1', 'port' => 63792),
      //slaves
      'node_5' => array('host' => '127.0.0.1', 'port' => 63795),
      'node_6' => array('host' => '127.0.0.1', 'port' => 63796),
    ),
    //replication information
    'master_of' => array(
      'node_1' => 'node_6',  //node_6 slaveof node_1 in redis6.conf
      'node_2' => 'node_5',  // node_5 slaveof node_2 in redis5.conf
    ),

    'default_node' => 'node_1'
);
