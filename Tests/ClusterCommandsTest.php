<?php
/**
 * This file is part of the RedisCluster package.
 *
 * @category RedisCluster
 * @package  RedisCluster
 * @author   (c) Salimane Adjao Moustapha <me@salimane.com>
 * @license  MIT http://www.opensource.org/licenses/mit-license.php
 * @version  GIT:258f9e4
 * @link     https://github.com/salimane/rediscluster-php
 */

require 'config.php';

/**
 * ClusterCommandsTest
 *
 * @category RedisCluster
 * @package  RedisCluster
 * @author   (c) Salimane Adjao Moustapha <me@salimane.com>
 * @license  MIT http://www.opensource.org/licenses/mit-license.php
 * @link     https://github.com/salimane/rediscluster-php
 */
class ClusterCommandsTest extends \PHPUnit_Framework_TestCase
{
    protected $client;

    public function get_client($masters_only = false)
    {
        global $cluster;

        return new RedisCluster\RedisCluster($cluster, 4, $masters_only);
    }

    public function setUp()
    {
        $this->client = $this->get_client();
        $this->client->flushdb();
    }

    public function tearDown()
    {
        $this->client->flushdb();
    }

    // GENERAL SERVER COMMANDS
    public function test_dbsize()
    {
        global $cluster;
        $this->client->set('a', 'foo');
        $this->client->set('b', 'bar');
        $this->assertEquals($this->client->dbsize(), 2);
    }

    public function test_masters_only()
    {
        $client = $this->get_client(true);
        foreach ($client->cluster['nodes'] as $alias => $server) {
            if (isset($client->cluster['master_of']) && !isset($client->cluster['master_of'][$alias])) {
                continue;
            }

            $this->assertEquals($client->cluster['nodes'][$alias], $client->cluster['slaves'][$alias.'_slave']);

        }
    }

    public function test_getnodefor()
    {
        $this->client->set('bar', 'foo');
        $node = $this->client->getnodefor('bar');
        $node = array_values($node);
        $node = $node[0];
        $rd = new \Redis();
        $rd->connect($node['host'], $node['port']);
        $rd->select(4);
        $this->assertEquals($this->client->get('bar'), $rd->get('bar'));
    }

    public function test_get_and_set()
    {
        // get and set can't be tested independently of each other
        $client = $this->get_client();
        $this->assertEquals($client->get('a'), false);
        $byte_string = 'value';
        $integer = 5;
        $unicode_string = mb_convert_encoding(pack('n', 3456), 'UTF-8', 'UTF-16BE') . 'abcd' . mb_convert_encoding(pack('n', 3421), 'UTF-8', 'UTF-16BE');
        $this->assertTrue($client->set('byte_string', $byte_string));
        $this->assertTrue($client->set('integer', 5));
        $this->assertTrue($client->set('unicode_string', $unicode_string));
        $this->assertEquals($client->get('byte_string'), $byte_string);
        $this->assertEquals($client->get('integer'), $integer);
        $this->assertEquals($client->get('unicode_string'), $unicode_string);
    }

    public function test_hash_tag()
    {
        $this->client->set('bar{foo}', 'bar');
        if (array_values($this->client->getnodefor('foo')) != array_values($this->client->getnodefor('bar'))) {
            $this->assertEquals($this->client->get('bar'), false);
        }
        $this->assertEquals($this->client->get('bar{foo}'), 'bar');
        //checking bar on the right node
        $node = $this->client->getnodefor('foo');
        $node = array_values($node);
        $node = $node[0];
        $rd = new \Redis();
        $rd->connect($node['host'], $node['port']);
        $rd->select(4);
        $this->assertEquals($rd->get('bar'), $this->client->get('bar{foo}'));
    }

    public function test_delete()
    {
        $this->assertEquals($this->client->del('a'), false);
        $this->client->set('a', 'foo');
        $this->assertEquals($this->client->del('a'), 1);
    }

    public function test_config()
    {
        $mems = $this->client->config('GET', 'maxmemory');
        foreach ($mems as $data) {
            $this->assertTrue(is_numeric($data['maxmemory']));
        }
    }

    public function test_echo()
    {
        $this->assertEquals($this->client->echo('foo bar'), 'foo bar');
    }

    public function test_info()
    {
        global $cluster;
        $this->client->set('a', 'foo');
        $this->client->set('b', 'bar');
        $kno = 0;
        $knoarr = array();
        $infos = $this->client->info();
        foreach ($infos as $node => $info) {
            $this->assertTrue(is_array($info));
            if (!empty($info['db4'])) {
                list($keys) = explode(',', $info['db4']);
                list($k, $v) = explode('=', $keys);
                if ($v && (isset($this->client->cluster['nodes'][$node]) && !isset($knoarr[$this->client->cluster['nodes'][$node]['host'].$this->client->cluster['nodes'][$node]['port']]))) {
                    $kno += $v;
                    $knoarr[$this->client->cluster['nodes'][$node]['host'].$this->client->cluster['nodes'][$node]['port']] = $v;
                }
            }
        }
        $this->assertEquals($kno, 2);
    }

    public function test_lastsave()
    {
        $datas = $this->client->lastsave();
        foreach ($datas as $data) {
            $this->assertTrue(is_integer($data));
        }
    }

    public function test_object()
    {
        $this->client->set('a', 'foo');
        $this->assertTrue(is_integer($this->client->object('refcount', 'a')));
        $this->assertTrue(is_integer($this->client->object('idletime', 'a')));
        $this->assertEquals($this->client->object('encoding', 'a'), 'raw');
    }

    public function test_ping()
    {
        $datas = $this->client->ping();
        foreach ($datas as $data) {
            $this->assertEquals($data, '+PONG');
        }
    }

    public function test_time()
    {
        $infos = $this->client->info();
        foreach ($infos as $info) {
            $version = $info['redis_version'];
            if (version_compare($version, "2.5.0", "<")) {
                $this->markTestSkipped();
            }
        }
        $times = $this->client->time();
        foreach ($times as  $t) {
            $this->assertEquals(count($t), 2);
            $this->assertTrue(is_integer((int) $t[0]));
            $this->assertTrue(is_integer((int) $t[1]));
        }
    }

    // KEYS
    public function test_append()
    {
        // invalid key type
        $this->client->rpush('a', 'a1');
        $this->assertEquals($this->client->append('a', 'a1'), false);
        $this->client->del('a');
        // real logic
        $this->assertEquals($this->client->append('a', 'a1'), 2);
        $this->assertEquals($this->client->get('a'), 'a1');
        $this->assertEquals($this->client->append('a', 'a2'), 4);
        $this->assertEquals($this->client->get('a'), 'a1a2');
    }

    public function test_getrange()
    {
        $this->client->set('a', 'foo');
        $this->assertEquals($this->client->getrange('a', 0, 0), 'f');
        $this->assertEquals($this->client->getrange('a', 0, 2), 'foo');
        $this->assertEquals($this->client->getrange('a', 3, 4), '');
    }

    public function test_decr()
    {
        $this->assertEquals($this->client->decr('a'), -1);
        $this->assertEquals($this->client->get('a'), '-1');
        $this->assertEquals($this->client->decr('a'), -2);
        $this->assertEquals($this->client->get('a'), '-2');
        $this->assertEquals($this->client->decr('a', 5), -7);
        $this->assertEquals($this->client->get('a'), '-7');
    }

    public function test_exists()
    {
        $this->assertEquals($this->client->exists('a'), false);
        $this->client->set('a', 'foo');
        $this->assertEquals($this->client->exists('a'), true);
    }

    public function test_expire()
    {
        $this->assertEquals($this->client->expire('a', 10), false);
        $this->client->set('a', 'foo');
        $this->assertEquals($this->client->expire('a', 10), true);
        $this->assertEquals($this->client->ttl('a'), 10);
        $this->assertEquals($this->client->persist('a'), true);
        $this->assertEquals($this->client->ttl('a'), -1);
    }

    public function test_expireat()
    {
        $expire_at = time() + 60;
        $this->assertEquals($this->client->expireat('a', $expire_at), false);
        $this->client->set('a', 'foo');
        // expire at in unix time
        $expire_at_seconds = $expire_at;
        $this->assertEquals($this->client->expireat('a', $expire_at_seconds), true);
        $this->assertGreaterThanOrEqual(58, $this->client->ttl('a'));
        // expire at given a datetime object
        $this->client->set('b', 'bar');
        $this->assertEquals($this->client->expireat('b', $expire_at), true);
        $this->assertGreaterThanOrEqual(58, $this->client->ttl('b'));
    }

    public function test_pexpire()
    {
        $infos = $this->client->info();
        foreach ($infos as $info) {
            $version = $info['redis_version'];
            if (version_compare($version, "2.5.0", "<")) {
                $this->markTestSkipped();
            }
        }

        $this->assertEquals($this->client->pexpire('a', 10000), false);
        $this->client->set('a', 'foo');
        $this->assertEquals($this->client->pexpire('a', 10000), true);
        $this->assertTrue($this->client->pttl('a') <= 10000);
        $this->assertEquals($this->client->persist('a'), true);
        $this->assertEquals($this->client->pttl('a'), -1);
    }

    public function test_pexpireat()
    {
        $infos = $this->client->info();
        foreach ($infos as $info) {
            $version = $info['redis_version'];
            if (version_compare($version, "2.5.0", "<")) {
                $this->markTestSkipped();
            }
        }

        $expire_at = time() + 60;
        $this->assertEquals($this->client->pexpireat('a', $expire_at), false);
        $this->client->set('a', 'foo');
        // expire at in unix time (milliseconds)
        $expire_at_seconds = $expire_at * 1000;
        $this->assertEquals($this->client->pexpireat('a', $expire_at_seconds), true);
        $this->assertTrue($this->client->ttl('a') <= 60);
        // expire at given time
        $this->client->set('b', 'bar');
        $this->assertEquals($this->client->pexpireat('b', $expire_at), true);
        $this->assertTrue($this->client->ttl('b') <= 60);
    }

    public function test_get_set_bit()
    {
        $this->assertEquals($this->client->getbit('a', 5), false);
        $this->assertEquals($this->client->setbit('a', 5, true), false);
        $this->assertEquals($this->client->getbit('a', 5), true);
        $this->assertEquals($this->client->setbit('a', 4, false), false);
        $this->assertEquals($this->client->getbit('a', 4), false);
        $this->assertEquals($this->client->setbit('a', 4, true), false);
        $this->assertEquals($this->client->setbit('a', 5, true), true);
        $this->assertEquals($this->client->getbit('a', 4), true);
        $this->assertEquals($this->client->getbit('a', 5), true);
    }

    public function test_bitcount()
    {
        $infos = $this->client->info();
        foreach ($infos as $info) {
            $version = $info['redis_version'];
            if (version_compare($version, "2.5.0", "<")) {
                $this->markTestSkipped();
            }
        }

        $this->client->setbit('a', 5, true);
        $this->assertEquals($this->client->bitcount('a'), 1);
        $this->client->setbit('a', 6, true);
        $this->assertEquals($this->client->bitcount('a'), 2);
        $this->client->setbit('a', 5, false);
        $this->assertEquals($this->client->bitcount('a'), 1);
        $this->client->setbit('a', 9, true);
        $this->client->setbit('a', 17, true);
        $this->client->setbit('a', 25, true);
        $this->client->setbit('a', 33, true);
        $this->assertEquals($this->client->bitcount('a'), 5);
        $this->assertEquals($this->client->bitcount('a', 2, 3), 2);
        $this->assertEquals($this->client->bitcount('a', 2, -1), 3);
        $this->assertEquals($this->client->bitcount('a', -2, -1), 2);
        $this->assertEquals($this->client->bitcount('a', 1, 1), 1);
    }

    public function test_bitop_not_empty_string()
    {
        $infos = $this->client->info();
        foreach ($infos as $info) {
            $version = $info['redis_version'];
            if (version_compare($version, "2.6.0", "<")) {
                $this->markTestSkipped();
            }
        }

        $this->client->set('a', '');
        $this->client->bitop('not', 'r', 'a');
        $this->assertEquals($this->client->get('r'), false);
    }

    public function test_bitop_not()
    {
        $this->markTestSkipped();
        $infos = $this->client->info();
        foreach ($infos as $info) {
            $version = $info['redis_version'];
            if (version_compare($version, "2.6.0", "<")) {
                $this->markTestSkipped();
            }
        }

        $test_str = '\xAA\x00\xFF\x55';
        $correct = ~0xAA00FF55 & 0xFFFFFFFF;
        $this->client->set('a', $test_str);
        $this->client->bitop('NOT', 'r', 'a');
        $this->assertEquals($this->client->get('r'), $correct);
    }

    public function test_bitop_not_in_place()
    {
        $this->markTestSkipped();
        $infos = $this->client->info();
        foreach ($infos as $info) {
            $version = $info['redis_version'];
            if (version_compare($version, "2.6.0", "<")) {
                $this->markTestSkipped();
            }
        }

        $test_str = '\xAA\x00\xFF\x55';
        $correct = ~0xAA00FF55 & 0xFFFFFFFF;
        $this->client->set('a', $test_str);
        $this->client->bitop('not', 'a', 'a');
        $this->assertEquals($this->client->get('a'), $correct);
    }

    public function test_bitop_single_string()
    {
        $this->markTestSkipped();
        $infos = $this->client->info();
        foreach ($infos as $info) {
            $version = $info['redis_version'];
            if (version_compare($version, "2.6.0", "<")) {
                $this->markTestSkipped();
            }
        }

        $test_str = '\x01\x02\xFF';
        $this->client->set('a', $test_str);
        $this->client->bitop('and', 'res1', 'a');
        $this->client->bitop('or', 'res2', 'a');
        $this->client->bitop('xor', 'res3', 'a');
        $this->assertEquals($this->client->get('res1'), $test_str);
        $this->assertEquals($this->client->get('res2'), $test_str);
        $this->assertEquals($this->client->get('res3'), $test_str);
    }

    public function test_bitop_string_operands()
    {
        $this->markTestSkipped();
        $infos = $this->client->info();
        foreach ($infos as $info) {
            $version = $info['redis_version'];
            if (version_compare($version, "2.6.0", "<")) {
                $this->markTestSkipped();
            }
        }

        $this->client->set('a', '\x01\x02\xFF\xFF');
        $this->client->set('b', '\x01\x02\xFF');
        $this->client->bitop('and', 'res1', 'a', 'b');
        $this->client->bitop('or', 'res2', 'a', 'b');
        $this->client->bitop('xor', 'res3', 'a', 'b');
        $this->assertEquals($this->client->get('res1'), 0x0102FF00);
        $this->assertEquals($this->client->get('res2'), 0x0102FFFF);
        $this->assertEquals($this->client->get('res3'), 0x000000FF);
    }

    public function test_getset()
    {
        $this->assertEquals($this->client->getset('a', 'foo'), false);
        $this->assertEquals($this->client->getset('a', 'bar'), 'foo');
    }

    public function test_incr()
    {
        $this->assertEquals($this->client->incr('a'), 1);
        $this->assertEquals($this->client->get('a'), '1');
        $this->assertEquals($this->client->incr('a'), 2);
        $this->assertEquals($this->client->get('a'), '2');
        $this->assertEquals($this->client->incr('a', 5), 7);
        $this->assertEquals($this->client->get('a'), '7');
    }

    public function test_incrbyfloat()
    {
        $infos = $this->client->info();
        foreach ($infos as $info) {
            $version = $info['redis_version'];
            if (version_compare($version, "2.5.0", "<")) {
                $this->markTestSkipped();
            }
        }

        $this->assertEquals($this->client->incrbyfloat('a', 1), 1.0);
        $this->assertEquals($this->client->get('a'), '1');
        $this->assertEquals($this->client->incrbyfloat('a', 1.1), 2.1);
        $this->assertEquals(floatval($this->client->get('a')), floatval(2.1));
    }

    public function test_keys()
    {
        $this->assertEquals($this->client->keys(), array());
        $keys = array('test_a', 'test_b', 'testc');
        foreach ($keys as $key) {
            $this->client->set($key, 1);
        }
        $results = $this->client->keys('test_*');
        sort($results);
        $this->assertEquals($results, array('test_a', 'test_b'));
        $results = $this->client->keys('test*');
        sort($results);
        $this->assertEquals($results, $keys);
    }

    public function test_mget()
    {
        $this->assertEquals($this->client->mget(array('a', 'b')), array(false, false));
        $this->client->set('a', '1');
        $this->client->set('b', '2');
        $this->client->set('c', '3');
        $this->assertEquals($this->client->mget(array('a', 'other', 'b', 'c')), array('1', false, '2', '3'));
    }

    public function test_mget_hash_tag()
    {
        $this->assertEquals($this->client->mget(array('foo{foo}', 'bar')), array(false, false));
        $this->client->set('foo', '1');
        $this->client->set('bar{foo}', '2');
        $this->client->set('other{foo}', '3');
        $this->assertEquals($this->client->mget(array('foo{foo}', 'c', 'bar', 'other')), array('1', false, '2', '3'));
    }

    public function test_mset()
    {
        $d = array('a' => '1', 'b' => '2', 'c' => '3');
        $this->assertTrue($this->client->mset($d));
        foreach ($d as $k => $v) {
            $this->assertEquals($this->client->get($k), $v);
        }
    }

    public function test_mset_mget_hash_tag()
    {
        $this->assertTrue($this->client->mset(array('foo{foo}' => '1', 'bar' => '2', 'other' => '3')));
        $this->assertEquals($this->client->mget(array('foo{foo}', 'bar', 'other')), array('1', '2', '3'));
        $this->assertEquals($this->client->get('foo'), '1');
        $this->assertEquals($this->client->get('bar{foo}'), '2');
        $this->assertEquals($this->client->get('other{foo}'), '3');
    }

    public function test_msetnx()
    {
        $d = array('a' => '1', 'b' => '2', 'c' => '3');
        $this->assertTrue($this->client->msetnx($d));
        $d2 = array('a' => 'x', 'd' => '4');
        $this->assertEquals($this->client->msetnx($d2), false);
        foreach ($d as $k => $v) {
            $this->assertEquals($this->client->get($k), $v);
        }
        $this->assertEquals($this->client->get('d'), false);
    }

    public function test_randomkey()
    {
        //CLUSTER
        $this->markTestSkipped();

        $this->assertEquals($this->client->randomkey(), false);
        $this->client->set('a', '1');
        $this->client->set('b', '2');
        $this->client->set('c', '3');
        $this->assertTrue(in_array($this->client->randomkey(), array('a', 'b', 'c')));
    }

    public function test_rename()
    {
        $this->client->set('a', '1');
        $this->assertTrue($this->client->rename('a', 'b'));
        $this->assertEquals($this->client->get('a'), false);
        $this->assertEquals($this->client->get('b'), '1');
    }

    public function test_renamenx()
    {
        $this->client->set('a', '1');
        $this->client->set('b', '2');
        $this->assertEquals($this->client->renamenx('a', 'b'), false);
        $this->assertEquals($this->client->get('a'), '1');
        $this->assertEquals($this->client->get('b'), '2');
    }

    public function test_setex()
    {
        $this->assertEquals($this->client->setex('a', 60, '1'), true);
        $this->assertEquals($this->client->get('a'), '1');
        $this->assertEquals($this->client->ttl('a'), 60);
    }

    public function test_setnx()
    {
        $this->assertTrue($this->client->setnx('a', '1'));
        $this->assertEquals($this->client->get('a'), '1');
        $this->assertEquals($this->client->setnx('a', '2'), false);
        $this->assertEquals($this->client->get('a'), '1');
    }

    public function test_setrange()
    {
        $this->assertEquals($this->client->setrange('a', 5, 'abcdef'), 11);
        $this->assertEquals($this->client->get('a'), "\0\0\0\0\0abcdef");
        $this->client->set('a', 'Hello World');
        $this->assertEquals($this->client->setrange('a', 6, 'Redis'), 11);
        $this->assertEquals($this->client->get('a'), 'Hello Redis');
    }

    public function test_strlen()
    {
        $this->client->set('a', 'abcdef');
        $this->assertEquals($this->client->strlen('a'), 6);
    }

    public function test_substr()
    {
        // invalid key type
        $this->client->rpush('a', 'a1');
        $this->assertEquals($this->client->substr('a', 0, -1), false);
        $this->client->del('a');
        // real logic
        $this->client->set('a', 'abcdefghi');
        $this->assertEquals($this->client->substr('a', 0, -1), 'abcdefghi');
        $this->assertEquals($this->client->substr('a', 2, -1), 'cdefghi');
        $this->assertEquals($this->client->substr('a', 3, 5), 'def');
        $this->assertEquals($this->client->substr('a', 3, -2), 'defgh');
        $this->client->set('a', 123456);  // does substr work with ints?
        $this->assertEquals($this->client->substr('a', 2, -2), '345');
    }

    public function test_type()
    {
        $this->assertEquals($this->client->type('a'), false);
        $this->client->set('a', '1');
        $this->assertEquals($this->client->type('a'), Redis::REDIS_STRING);
        $this->client->del('a');
        $this->client->lpush('a', '1');
        $this->assertEquals($this->client->type('a'), Redis::REDIS_LIST);
        $this->client->del('a');
        $this->client->sadd('a', '1');
        $this->assertEquals($this->client->type('a'), Redis::REDIS_SET);
        $this->client->del('a');
        $this->client->zadd('a', '1', 1);
        $this->assertEquals($this->client->type('a'), Redis::REDIS_ZSET);
    }

    // LISTS
    public function make_list($name, $l)
    {
        !is_array($l) && $l = str_split($l);
        foreach ($l as $i) {
            $this->client->rpush($name, $i);
        }
    }

    public function test_blpop()
    {
        //CLUSTER
        $this->make_list('a', 'ab');
        $this->make_list('b', 'cd');
        $this->assertEquals($this->client->blpop('b', 1), array('b', 'c'));
        $this->assertEquals($this->client->blpop('b', 1), array('b', 'd'));
        $this->assertEquals($this->client->blpop('a', 1), array('a', 'a'));
        $this->assertEquals($this->client->blpop('a', 1), array('a', 'b'));
        $this->assertEquals($this->client->blpop('b', 1), array());
        $this->assertEquals($this->client->blpop('a', 1), array());
        $this->make_list('c', 'a');
        $this->assertEquals($this->client->blpop('c', 1), array('c', 'a'));
    }

    public function test_brpop()
    {
        $this->make_list('a', 'ab');
        $this->make_list('b', 'cd');
        $this->assertEquals($this->client->brpop('b', 1), array('b', 'd'));
        $this->assertEquals($this->client->brpop('b', 1), array('b', 'c'));
        $this->assertEquals($this->client->brpop('a', 1), array('a', 'b'));
        $this->assertEquals($this->client->brpop('a', 1), array('a', 'a'));
        $this->assertEquals($this->client->brpop('b', 1), array());
        $this->assertEquals($this->client->brpop('a', 1), array());
        $this->make_list('c', 'a');
        $this->assertEquals($this->client->brpop('c', 1), array('c', 'a'));
    }

    public function test_brpoplpush()
    {
        $this->make_list('a', '12');
        $this->make_list('b', '34');
        $this->assertEquals($this->client->brpoplpush('a', 'b', 0), '2');
        $this->assertEquals($this->client->brpoplpush('a', 'b', 0), '1');
        $this->assertEquals($this->client->brpoplpush('a', 'b', 1), false);
        $this->assertEquals($this->client->lrange('a', 0, -1), array());
        $this->assertEquals($this->client->lrange('b', 0, -1), array('1', '2', '3', '4'));
    }

    public function test_lindex()
    {
        // no key
        $this->assertEquals($this->client->lindex('a', '0'), false);
        // key is not a list
        $this->client->set('a', 'b');
        $this->assertEquals($this->client->lindex('a', '0'), false);
        $this->client->del('a');
        // real logic
        $this->make_list('a', 'abc');
        $this->assertEquals($this->client->lindex('a', '0'), 'a');
        $this->assertEquals($this->client->lindex('a', '1'), 'b');
        $this->assertEquals($this->client->lindex('a', '2'), 'c');
    }

    public function test_linsert()
    {
        // no key
        $this->assertEquals($this->client->linsert('a', 'after', 'x', 'y'), 0);
        // key is not a list
        $this->client->set('a', 'b');
        $this->assertEquals($this->client->linsert('a', 'after', 'x', 'y'), false);
        $this->client->del('a');
        // real logic
        $this->make_list('a', 'abc');
        $this->assertEquals($this->client->linsert('a', 'after', 'b', 'b1'), 4);
        $this->assertEquals($this->client->lrange('a', 0, -1), array('a', 'b', 'b1', 'c'));
        $this->assertEquals($this->client->linsert('a', 'before', 'b', 'a1'), 5);
        $this->assertEquals($this->client->lrange('a', 0, -1), array('a', 'a1', 'b', 'b1', 'c'));
    }

    public function test_llen()
    {
        // no key
        $this->assertEquals($this->client->llen('a'), 0);
        // key is not a list
        $this->client->set('a', 'b');
        $this->assertEquals($this->client->llen('a'), false);
        $this->client->del('a');
        // real logic
        $this->make_list('a', 'abc');
        $this->assertEquals($this->client->llen('a'), 3);
    }

    public function test_lpop()
    {
        // no key
        $this->assertEquals($this->client->lpop('a'), false);
        // key is not a list
        $this->client->set('a', 'b');
        $this->assertEquals($this->client->lpop('a'), false);
        $this->client->del('a');
        // real logic
        $this->make_list('a', 'abc');
        $this->assertEquals($this->client->lpop('a'), 'a');
        $this->assertEquals($this->client->lpop('a'), 'b');
        $this->assertEquals($this->client->lpop('a'), 'c');
        $this->assertEquals($this->client->lpop('a'), false);
    }

    public function test_lpush()
    {
        // key is not a list
        $this->client->set('a', 'b');
        $this->assertEquals($this->client->lpush('a', 'a'), false);
        $this->client->del('a');
        // real logic
        $this->assertEquals(1, $this->client->lpush('a', 'b'));
        $this->assertEquals(2, $this->client->lpush('a', 'a'));
        $infos = $this->client->info();
        foreach ($infos as $info) {
            $version = $info['redis_version'];
            if (version_compare($version, "2.4.0", ">=")) {
                $this->assertEquals(4, $this->client->lpush('a', 'b', 'a'));
                break;
            }
        }

        $this->assertEquals($this->client->lindex('a', 0), 'a');
        $this->assertEquals($this->client->lindex('a', 1), 'b');
    }

    public function test_lpushx()
    {
        // key is not a list
        $this->client->set('a', 'b');
        $this->assertEquals($this->client->lpushx('a', 'a'), false);
        $this->client->del('a');
        // real logic
        $this->assertEquals($this->client->lpushx('a', 'b'), 0);
        $this->assertEquals($this->client->lrange('a', 0, -1), array());
        $this->make_list('a', 'abc');
        $this->assertEquals($this->client->lpushx('a', 'd'), 4);
        $this->assertEquals($this->client->lrange('a', 0, -1), array('d', 'a', 'b', 'c'));
    }

    public function test_lrange()
    {
        // no key
        $this->assertEquals($this->client->lrange('a', 0, 1), array());
        // key is not a list
        $this->client->set('a', 'b');
        $this->assertEquals($this->client->lrange('a', 0, 1), false);
        $this->client->del('a');
        // real logic
        $this->make_list('a', 'abcde');
        $this->assertEquals($this->client->lrange('a', 0, 2), array('a', 'b', 'c'));
        $this->assertEquals($this->client->lrange('a', 2, 10), array('c', 'd', 'e'));
    }

    public function test_lrem()
    {
        // no key
        $this->assertEquals($this->client->lrem('a', 'foo', 0), 0);
        // key is not a list
        $this->client->set('a', 'b');
        $this->assertEquals($this->client->lrem('a', 'b', 0), false);
        $this->client->del('a');
        // real logic
        $this->make_list('a', 'aaaa');
        $this->assertEquals($this->client->lrem('a', 'a', 1), 1);
        $this->assertEquals($this->client->lrange('a', 0, 3), array('a', 'a', 'a'));
        $this->assertEquals($this->client->lrem('a', 'a', 0), 3);
        // remove all the elements in the list means the key is deleted
        $this->assertEquals($this->client->lrange('a', 0, 1), array());
    }

    public function test_lset()
    {
        // no key
        $this->assertEquals($this->client->lset('a', 1, 'b'), false);
        // key is not a list
        $this->client->set('a', 'b');
        $this->assertEquals($this->client->lset('a', 1, 'b'), false);
        $this->client->del('a');
        // real logic
        $this->make_list('a', 'abc');
        $this->assertEquals($this->client->lrange('a', 0, 2), array('a', 'b', 'c'));
        $this->assertTrue($this->client->lset('a', 1, 'd'));
        $this->assertEquals($this->client->lrange('a', 0, 2), array('a', 'd', 'c'));
    }

    public function test_ltrim()
    {
        // no key -- TODO: Not sure why this is actually true.
        $this->assertTrue($this->client->ltrim('a', 0, 2));
        // key is not a list
        $this->client->set('a', 'b');
        $this->assertEquals($this->client->ltrim('a', 0, 2), false);
        $this->client->del('a');
        // real logic
        $this->make_list('a', 'abc');
        $this->assertTrue($this->client->ltrim('a', 0, 1));
        $this->assertEquals($this->client->lrange('a', 0, 5), array('a', 'b'));
    }

    public function test_rpop()
    {
        // no key
        $this->assertEquals($this->client->rpop('a'), false);
        // key is not a list
        $this->client->set('a', 'b');
        $this->assertEquals($this->client->rpop('a'), false);
        $this->client->del('a');
        // real logic
        $this->make_list('a', 'abc');
        $this->assertEquals($this->client->rpop('a'), 'c');
        $this->assertEquals($this->client->rpop('a'), 'b');
        $this->assertEquals($this->client->rpop('a'), 'a');
        $this->assertEquals($this->client->rpop('a'), false);
    }

    public function test_rpoplpush()
    {
        // no src key
        $this->make_list('b', array('b1'));
        $this->assertEquals($this->client->rpoplpush('a', 'b'), false);
        // no dest key
        $this->assertEquals($this->client->rpoplpush('b', 'a'), 'b1');
        $this->assertEquals($this->client->lindex('a', 0), 'b1');
        $this->client->del('a');
        $this->client->del('b');
        // src key is not a list
        $this->client->set('a', 'a1');
        $this->assertEquals($this->client->rpoplpush('a', 'b'), false);
        $this->client->del('a');
        // dest key is not a list
        $this->make_list('a', array('a1'));
        $this->client->set('b', 'b');
        $this->assertEquals($this->client->rpoplpush('a', 'b'), false);
        $this->client->del('a');
        $this->client->del('b');
        // real logic
        $this->make_list('a', array('a1', 'a2', 'a3'));
        $this->make_list('b', array('b1', 'b2', 'b3'));
        $this->assertEquals($this->client->rpoplpush('a', 'b'), 'a3');
        $this->assertEquals($this->client->lrange('a', 0, 2), array('a1', 'a2'));
        $this->assertEquals($this->client->lrange('b', 0, 4), array('a3', 'b1', 'b2', 'b3'));
    }

    public function test_rpush()
    {
        // key is not a list
        $this->client->set('a', 'b');
        $this->assertEquals($this->client->rpush('a', 'a'), false);
        $this->client->del('a');
        // real logic
        $this->assertEquals(1, $this->client->rpush('a', 'a'));
        $this->assertEquals(2, $this->client->rpush('a', 'b'));
        $infos = $this->client->info();
        foreach ($infos as $info) {
            $version = $info['redis_version'];
            if (version_compare($version, "2.4.0", ">=")) {
                $this->assertEquals(4, $this->client->rpush('a', 'b', 'a'));
                break;
            }
        }

        $this->assertEquals($this->client->lindex('a', 0), 'a');
        $this->assertEquals($this->client->lindex('a', 1), 'b');
    }

    public function test_rpushx()
    {
        // key is not a list
        $this->client->set('a', 'b');
        $this->assertEquals($this->client->rpushx('a', 'a'), false);
        $this->client->del('a');
        // real logic
        $this->assertEquals($this->client->rpushx('a', 'b'), 0);
        $this->assertEquals($this->client->lrange('a', 0, -1), array());
        $this->make_list('a', 'abc');
        $this->assertEquals($this->client->rpushx('a', 'd'), 4);
        $this->assertEquals($this->client->lrange('a', 0, -1), array('a', 'b', 'c', 'd'));
    }

    // Set commands
    public function make_set($name, $l)
    {
        !is_array($l) && $l = str_split($l);
        foreach ($l as $i) {
            $this->client->sadd($name, $i);
        }
    }

    public function test_sadd()
    {
        // key is not a set
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->sadd('a', 'a1'), false);
        $this->client->del('a');
        // real logic
        $members = array('a1', 'a2', 'a3');
        $this->make_set('a', $members);
        $actual = $this->client->smembers('a');
        sort($actual);
        $this->assertEquals($actual, $members);
    }

    public function test_scard()
    {
        // key is not a set
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->scard('a'), false);
        $this->client->del('a');
        // real logic
        $this->make_set('a', 'abc');
        $this->assertEquals($this->client->scard('a'), 3);
    }

    public function test_sdiff()
    {
        // some key is not a set
        $this->make_set('a', array('a1', 'a2', 'a3'));
        $this->client->set('b', 'b');
        $this->assertEquals($this->client->sdiff('a', 'b'), false);
        $this->client->del('b');
        // real logic
        $this->make_set('b', array('b1', 'a2', 'b3'));
        $actual = $this->client->sdiff('a', 'b');
        sort($actual);
        $this->assertEquals($actual, array('a1', 'a3'));
    }

    public function test_sdiffstore()
    {
        // some key is not a set
        $this->make_set('a', array('a1', 'a2', 'a3'));
        $this->client->set('b', 'b');
        $this->assertEquals($this->client->sdiffstore('c', 'a', 'b'), false);
        $this->client->del('b');
        $this->make_set('b', array('b1', 'a2', 'b3'));
        // dest key always gets overwritten, even if it's not a set, so don't
        // test for that
        // real logic
        $this->assertEquals($this->client->sdiffstore('c', 'a', 'b'), 2);
        $actual = $this->client->smembers('c');
        sort($actual);
        $this->assertEquals($actual, array('a1', 'a3'));
    }

    public function test_sinter()
    {
        // some key is not a set
        $this->make_set('a', array('a1', 'a2', 'a3'));
        $this->client->set('b', 'b');
        $this->assertEquals($this->client->sinter('a', 'b'), false);
        $this->client->del('b');
        // real logic
        $this->make_set('b', array('a1', 'b2', 'a3'));
        $actual = $this->client->sinter('a', 'b');
        sort($actual);
        $this->assertEquals($actual, array('a1', 'a3'));
    }

    public function test_sinterstore()
    {
        // some key is not a set
        $this->make_set('a', array('a1', 'a2', 'a3'));
        $this->client->set('b', 'b');
        $this->assertEquals($this->client->sinterstore('c', 'a', 'b'), false);
        $this->client->del('b');
        $this->make_set('b', array('a1', 'b2', 'a3'));
        // dest key always gets overwritten, even if it's not a set, so don't
        // test for that
        // real logic
        $this->assertEquals($this->client->sinterstore('c', 'a', 'b'), 2);
        $actual = $this->client->smembers('c');
        sort($actual);
        $this->assertEquals($actual, array('a1', 'a3'));
    }

    public function test_sismember()
    {
        // key is not a set
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->sismember('a', 'a'), false);
        $this->client->del('a');
        // real logic
        $this->make_set('a', 'abc');
        $this->assertEquals($this->client->sismember('a', 'a'), true);
        $this->assertEquals($this->client->sismember('a', 'b'), true);
        $this->assertEquals($this->client->sismember('a', 'c'), true);
        $this->assertEquals($this->client->sismember('a', 'd'), false);
    }

    public function test_smembers()
    {
        // key is not a set
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->smembers('a'), false);
        $this->client->del('a');
        // set doesn't exist
        $this->assertEquals($this->client->smembers('a'), array());
        // real logic
        $this->make_set('a', 'abc');
        $res = $this->client->smembers('a');
        sort($res);
        $this->assertEquals($res, array('a', 'b', 'c'));
    }

    public function test_smove()
    {
        // src key is not set
        $this->make_set('b', array('b1', 'b2'));
        $this->assertEquals($this->client->smove('a', 'b', 'a1'), 0);
        // src key is not a set
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->smove('a', 'b', 'a1'), false);
        $this->client->del('a');
        $this->make_set('a', array('a1', 'a2'));
        // dest key is not a set
        $this->client->del('b');
        $this->client->set('b', 'b');
        $this->assertEquals($this->client->smove('a', 'b', 'a1'), false);
        $this->client->del('b');
        $this->make_set('b', array('b1', 'b2'));
        // real logic
        $this->assertTrue($this->client->smove('a', 'b', 'a1'));
        $this->assertEquals($this->client->smembers('a'), array('a2'));
        $res = $this->client->smembers('b');
        sort($res);
        $this->assertEquals($res, array('a1', 'b1', 'b2'));
    }

    public function test_spop()
    {
        // key is not set
        $this->assertEquals($this->client->spop('a'), false);
        // key is not a set
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->spop('a'), false);
        $this->client->del('a');
        // real logic
        $s = array('a', 'b', 'c');
        $this->make_set('a', $s);
        $value = $this->client->spop('a');
        $this->assertTrue(in_array($value, $s));
        $expected = array_values(array_diff($s, array($value)));
        $actual = $this->client->smembers('a');
        sort($actual);
        $this->assertEquals($actual, $expected);
    }

    public function test_srandmember()
    {
        // key is not set
        $this->assertEquals($this->client->srandmember('a'), false);
        // key is not a set
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->srandmember('a'), false);
        $this->client->del('a');
        // real logic
        $this->make_set('a', 'abc');
        $this->assertTrue(in_array($this->client->srandmember('a'), array('a', 'b', 'c')));
    }

    public function test_srem()
    {
        // key is not set
        $this->assertEquals($this->client->srem('a', 'a'), false);
        // key is not a set
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->srem('a', 'a'), false);
        $this->client->del('a');
        // real logic
        $this->make_set('a', 'abc');
        $this->assertEquals($this->client->srem('a', 'd'), false);
        $this->assertEquals($this->client->srem('a', 'b'), true);
        $actual = $this->client->smembers('a');
        sort($actual);
        $this->assertEquals($actual, array('a', 'c'));
    }

    public function test_sunion()
    {
        // some key is not a set
        $this->make_set('a', array('a1', 'a2', 'a3'));
        $this->client->set('b', 'b');
        $this->assertEquals($this->client->sunion('a', 'b'), false);
        $this->client->del('b');
        // real logic
        $this->make_set('b', array('a1', 'b2', 'a3'));
        $actual = $this->client->sunion('a', 'b');
        sort($actual);
        $this->assertEquals($actual, array('a1', 'a2', 'a3', 'b2'));
    }

    public function test_sunionstore()
    {
        // some key is not a set
        $this->make_set('a', array('a1', 'a2', 'a3'));
        $this->client->set('b', 'b');
        $this->assertEquals($this->client->sunionstore('c', 'a', 'b'), false);
        $this->client->del('b');
        $this->make_set('b', array('a1', 'b2', 'a3'));
        // dest key always gets overwritten, even if it's not a set, so don't
        // test for that
        // real logic
        $this->assertEquals($this->client->sunionstore('c', 'a', 'b'), 4);
        $actual = $this->client->smembers('c');
        sort($actual);
        $this->assertEquals($actual, array('a1', 'a2', 'a3', 'b2'));
    }

    // SORTED SETS
    public function make_zset($name, $d)
    {
        foreach ($d as $k => $v) {
            $this->client->zadd($name, $v, $k);
        }
    }

    public function test_zadd()
    {
        $this->make_zset('a', array('a1' => 1, 'a2' => 2, 'a3' => 3));
        $this->assertEquals($this->client->zrange('a', 0, 3), array('a1', 'a2', 'a3'));
    }

    public function test_zcard()
    {
        // key is not a zset
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->zcard('a'), false);
        $this->client->del('a');
        // real logic
        $this->make_zset('a', array('a1' => 1, 'a2' => 2, 'a3' => 3));
        $this->assertEquals($this->client->zcard('a'), 3);
    }

    public function test_zcount()
    {
        // key is not a zset
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->zcount('a', 0, 0), false);
        $this->client->del('a');
        // real logic
        $this->make_zset('a', array('a1' => 1, 'a2' => 2, 'a3' => 3));
        $this->assertEquals($this->client->zcount('a', '-inf', '+inf'), 3);
        $this->assertEquals($this->client->zcount('a', 1, 2), 2);
        $this->assertEquals($this->client->zcount('a', 10, 20), 0);
    }

    public function test_zincrby()
    {
        // key is not a zset
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->zincrby('a', 1, 'a1'), false);
        $this->client->del('a');
        // real logic
        $this->make_zset('a', array('a1' => 1, 'a2' => 2, 'a3' => 3));
        $this->assertEquals($this->client->zincrby('a', 1, 'a2'), 3.0);
        $this->assertEquals($this->client->zincrby('a', 5, 'a3'), 8.0);
        $this->assertEquals($this->client->zscore('a', 'a2'), 3.0);
        $this->assertEquals($this->client->zscore('a', 'a3'), 8.0);
    }

    public function test_zinterstore()
    {
        //CLUSTER
        $this->markTestSkipped();
        $this->make_zset('a', array('a1' => 1, 'a2' => 1, 'a3' => 1));
        $this->make_zset('b', array('a1' => 2, 'a3' => 2, 'a4' => 2));
        $this->make_zset('c', array('a1' => 6, 'a3' => 5, 'a4' => 4));

        // sum, no weight
        $this->assertTrue($this->client->zinterstore('z', array('a', 'b', 'c')));
        $this->assertEquals($this->client->zrange('z', 0, -1, true),	array('a3' => 8, 'a1' => 9));

        // max, no weight
        $this->assertTrue($this->client->zinterstore('z', array('a', 'b', 'c'), 'max'));
        $this->assertEquals($this->client->zrange('z', 0, -1, true),	array('a3' => 5, 'a1' => 6));

        // with weight
        $this->assertTrue($this->client->zinterstore('z', array('a' => 1, 'b' => 2, 'c' => 3)));
        $this->assertEquals($this->client->zrange('z', 0, -1, true),	array('a3' => 20, 'a1' => 23));
    }

    public function test_zrange()
    {
        // key is not a zset
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->zrange('a', 0, 1), false);
        $this->client->del('a');
        // real logic
        $this->make_zset('a', array('a1' => 1, 'a2' => 2, 'a3' => 3));
        $this->assertEquals($this->client->zrange('a', 0, 1), array('a1', 'a2'));
        $this->assertEquals($this->client->zrange('a', 1, 2), array('a2', 'a3'));
        $this->assertEquals($this->client->zrange('a', 0, 1, true),	array('a1' => 1.0, 'a2' => 2.0));
        $this->assertEquals($this->client->zrange('a', 1, 2, true),	array('a2' => 2.0, 'a3' => 3.0));
        // a non existant key should return empty list
        $this->assertEquals($this->client->zrange('b', 0, 1, true), array());
    }

    public function test_zrangebyscore()
    {
        // key is not a zset
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->zrangebyscore('a', 0, 1), false);
        $this->client->del('a');
        // real logic
        $this->make_zset('a', array('a1' => 1, 'a2' => 2, 'a3' => 3, 'a4' => 4, 'a5' => 5));
        $this->assertEquals($this->client->zrangebyscore('a', 2, 4), array('a2', 'a3', 'a4'));
        $this->assertEquals($this->client->zrangebyscore('a', 2, 4, array('limit' => array(1, 2))), array('a3', 'a4'));
        $this->assertEquals(
                        $this->client->zrangebyscore(
                                        'a', 2, 4, array('withscores' => true)
                        ),
                        array('a2' => 2.0, 'a3' => 3.0, 'a4' => 4.0)
        );
        // a non existant key should return empty list
        $this->assertEquals(
                        $this->client->zrangebyscore('b', 0, 1, array('withscores' => true)),
                        array()
        );
    }

    public function test_zrank()
    {
        // key is not a zset
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->zrank('a', 'a4'), false);
        $this->client->del('a');
        // real logic
        $this->make_zset('a', array('a1' => 1, 'a2' => 2, 'a3' => 3, 'a4' => 4, 'a5' => 5));
        $this->assertEquals($this->client->zrank('a', 'a1'), 0);
        $this->assertEquals($this->client->zrank('a', 'a2'), 1);
        $this->assertEquals($this->client->zrank('a', 'a3'), 2);
        $this->assertEquals($this->client->zrank('a', 'a4'), 3);
        $this->assertEquals($this->client->zrank('a', 'a5'), 4);
        // non-existent value in zset
        $this->assertEquals($this->client->zrank('a', 'a6'), false);
    }

    public function test_zrem()
    {
        // key is not a zset
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->zrem('a', 'a1'), false);
        $this->client->del('a');
        // real logic
        $this->make_zset('a', array('a1' => 1, 'a2' => 2, 'a3' => 3));
        $this->assertEquals($this->client->zrem('a', 'a2'), true);
        $this->assertEquals($this->client->zrange('a', 0, 5), array('a1', 'a3'));
        $this->assertEquals($this->client->zrem('a', 'b'), false);
        $this->assertEquals($this->client->zrange('a', 0, 5), array('a1', 'a3'));
    }

    public function test_zremrangebyrank()
    {
        // key is not a zset
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->zremrangebyscore('a', 0, 1), false);
        $this->client->del('a');
        // real logic
        $this->make_zset('a', array('a1' => 1, 'a2' => 2, 'a3' => 3, 'a4' => 4, 'a5' => 5));
        $this->assertEquals($this->client->zremrangebyrank('a', 1, 3), 3);
        $this->assertEquals($this->client->zrange('a', 0, 5), array('a1', 'a5'));
    }

    public function test_zremrangebyscore()
    {
        // key is not a zset
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->zremrangebyscore('a', 0, 1), false);
        $this->client->del('a');
        // real logic
        $this->make_zset('a', array('a1' => 1, 'a2' => 2, 'a3' => 3, 'a4' => 4, 'a5' => 5));
        $this->assertEquals($this->client->zremrangebyscore('a', 2, 4), 3);
        $this->assertEquals($this->client->zrange('a', 0, 5), array('a1', 'a5'));
        $this->assertEquals($this->client->zremrangebyscore('a', 2, 4), 0);
        $this->assertEquals($this->client->zrange('a', 0, 5), array('a1', 'a5'));
    }

    public function test_zrevrange()
    {
        // key is not a zset
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->zrevrange('a', 0, 1), false);
        $this->client->del('a');
        // real logic
        $this->make_zset('a', array('a1' => 1, 'a2' => 2, 'a3' => 3));
        $this->assertEquals($this->client->zrevrange('a', 0, 1), array('a3', 'a2'));
        $this->assertEquals($this->client->zrevrange('a', 1, 2), array('a2', 'a1'));
        $this->assertEquals($this->client->zrevrange('a', 0, 1, true),	array('a3' => 3.0, 'a2' => 2.0));
        $this->assertEquals($this->client->zrevrange('a', 1, 2, true),	array('a2' => 2.0, 'a1' => 1.0));
        // a non existant key should return empty list
        $this->assertEquals($this->client->zrange('b', 0, 1, true), array());
    }

    public function test_zrevrangebyscore()
    {
        // key is not a zset
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->zrevrangebyscore('a', 0, 1), false);
        $this->client->del('a');
        // real logic
        $this->make_zset('a', array('a1' => 1, 'a2' => 2, 'a3' => 3, 'a4' => 4, 'a5' => 5));
        $this->assertEquals($this->client->zrevrangebyscore('a', 4, 2),	array('a4', 'a3', 'a2'));
        $this->assertEquals($this->client->zrevrangebyscore('a', 4, 2, array('limit' => array(1, 2))), array('a3', 'a2'));
        $this->assertEquals($this->client->zrevrangebyscore('a', 4, 2, array('withscores' => true)), array('a4' => 4.0, 'a3' => 3.0, 'a2' => 2.0));
        // a non existant key should return empty list
        $this->assertEquals($this->client->zrevrangebyscore('b', 1, 0, array('withscores' => true)), array());
    }

    public function test_zrevrank()
    {
        // key is not a zset
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->zrevrank('a', 'a4'), false);
        $this->client->del('a');
        // real logic
        $this->make_zset('a', array('a1' => 5, 'a2' => 4, 'a3' => 3, 'a4' => 2, 'a5' => 1));
        $this->assertEquals($this->client->zrevrank('a', 'a1'), 0);
        $this->assertEquals($this->client->zrevrank('a', 'a2'), 1);
        $this->assertEquals($this->client->zrevrank('a', 'a3'), 2);
        $this->assertEquals($this->client->zrevrank('a', 'a4'), 3);
        $this->assertEquals($this->client->zrevrank('a', 'a5'), 4);
        $this->assertEquals($this->client->zrevrank('a', 'b'), false);
    }

    public function test_zscore()
    {
        // key is not a zset
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->zscore('a', 'a1'), false);
        $this->client->del('a');
        // real logic
        $this->make_zset('a', array('a1' => 0, 'a2' => 1, 'a3' => 2));
        $this->assertEquals($this->client->zscore('a', 'a1'), 0.0);
        $this->assertEquals($this->client->zscore('a', 'a2'), 1.0);
        // test a non-existant member
        $this->assertEquals($this->client->zscore('a', 'a4'), false);
    }

    public function test_zunionstore()
    {
        //CLUSTER
        $this->markTestSkipped();

        $this->make_zset('a', array('a1' => 1, 'a2' => 1, 'a3' => 1));
        $this->make_zset('b', array('a1' => 2, 'a3' => 2, 'a4' => 2));
        $this->make_zset('c', array('a1' => 6, 'a4' => 5, 'a5' => 4));

        // sum, no weight
        $this->assertTrue($this->client->zunionstore('z', array('a', 'b', 'c')));
        $this->assertEquals($this->client->zrange('z', 0, -1, true),	array('a2' => 1, 'a3' => 3,	'a5' => 4, 'a4' => 7,	'a1' => 9));

        // max, no weight
        $this->assertTrue($this->client->zunionstore('z', array('a', 'b', 'c'), 'max'));
        $this->assertEquals($this->client->zrange('z', 0, -1, true), array('a2' => 1, 'a3' => 2, 'a5' => 4, 'a4' => 5, 'a1' => 6));

        // with weight
        $this->assertTrue($this->client->zunionstore('z', array('a' => 1, 'b' => 2, 'c' => 3)));
        $this->assertEquals($this->client->zrange('z', 0, -1, true), array('a2' => 1, 'a3' => 5, 'a5' => 12, 'a4' => 19, 'a1' => 23));
    }

    // HASHES
    public function make_hash($key, $d)
    {
        foreach ($d as $k => $v) {
            $this->client->hset($key, $k, $v);
        }
    }

    public function test_hget_and_hset()
    {
        // key is not a hash
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->hget('a', 'a1'), false);
        $this->client->del('a');
        // no key
        $this->assertEquals($this->client->hget('a', 'a1'), false);
        // real logic
        $this->make_hash('a', array('a1' => 1, 'a2' => 2, 'a3' => 3));
        $this->assertEquals($this->client->hget('a', 'a1'), '1');
        $this->assertEquals($this->client->hget('a', 'a2'), '2');
        $this->assertEquals($this->client->hget('a', 'a3'), '3');
        // field was updated, redis returns 0
        $this->assertEquals($this->client->hset('a', 'a2', 5), 0);
        $this->assertEquals($this->client->hget('a', 'a2'), '5');
        // field is new, redis returns 1
        $this->assertEquals($this->client->hset('a', 'a4', 4), 1);
        $this->assertEquals($this->client->hget('a', 'a4'), '4');
        // key inside of hash that doesn't exist returns null value
        $this->assertEquals($this->client->hget('a', 'b'), false);
    }

    public function test_hsetnx()
    {
        // Initially set the hash field
        $this->client->hsetnx('a', 'a1', 1);
        $this->assertEquals($this->client->hget('a', 'a1'), '1');
        // Try and set the existing hash field to a different value
        $this->client->hsetnx('a', 'a1', 2);
        $this->assertEquals($this->client->hget('a', 'a1'), '1');
    }

    public function test_hmset()
    {
        $d = array('a' => '1', 'b' => '2', 'c' => '3');
        $this->assertTrue($this->client->hmset('foo', $d));
        $this->assertEquals($this->client->hgetall('foo'), $d);
        $this->assertEquals($this->client->hmset('foo', array()), false);
    }

    public function test_hmset_empty_value()
    {
        $d = array('a' => '1', 'b' => '2', 'c' => '');
        $this->assertTrue($this->client->hmset('foo', $d));
        $this->assertEquals($this->client->hgetall('foo'), $d);
    }

    public function test_hmget()
    {
        $d = array('a' => 1, 'b' => 2, 'c' => 3);
        $this->assertTrue($this->client->hmset('foo', $d));
        $this->assertEquals($this->client->hmget('foo', array('a', 'b', 'c')), array('a' => '1', 'b' => '2', 'c' => '3'));
        $this->assertEquals($this->client->hmget('foo', array('a', 'c')), array('a' => '1', 'c' => '3'));
    }

    public function test_hmget_empty()
    {
        $this->assertEquals($this->client->hmget('foo', array('a', 'b')), array('a' => false, 'b' => false));
    }

    public function test_hmget_no_keys()
    {
        $this->assertEquals($this->client->hmget('foo', array()), false);
    }

    public function test_hdel()
    {
        // key is not a hash
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->hdel('a', 'a1'), false);
        $this->client->del('a');
        // no key
        $this->assertEquals($this->client->hdel('a', 'a1'), false);
        // real logic
        $this->make_hash('a', array('a1' => 1, 'a2' => 2, 'a3' => 3));
        $this->assertEquals($this->client->hget('a', 'a2'), '2');
        $this->assertEquals(1, $this->client->hdel('a', 'a2'));
        $this->assertEquals($this->client->hget('a', 'a2'), false);
    }

    public function test_hexists()
    {
        // key is not a hash
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->hexists('a', 'a1'), false);
        $this->client->del('a');
        // no key
        $this->assertEquals($this->client->hexists('a', 'a1'), false);
        // real logic
        $this->make_hash('a', array('a1' => 1, 'a2' => 2, 'a3' => 3));
        $this->assertEquals($this->client->hexists('a', 'a1'), true);
        $this->assertEquals($this->client->hexists('a', 'a4'), false);
        $this->client->hdel('a', 'a1');
        $this->assertEquals($this->client->hexists('a', 'a1'), false);
    }

    public function test_hgetall()
    {
        // key is not a hash
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->hgetall('a'), false);
        $this->client->del('a');
        // no key
        $this->assertEquals($this->client->hgetall('a'), array());
        // real logic
        $h = array('a1' => '1', 'a2' => '2', 'a3' => '3');
        $this->make_hash('a', $h);
        $remote_hash = $this->client->hgetall('a');
        $this->assertEquals($h, $remote_hash);
    }

    public function test_hincrby()
    {
        // key is not a hash
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->hincrby('a', 'a1', 1), false);
        $this->client->del('a');
        // no key should create the hash and incr the key's value to 1
        $this->assertEquals($this->client->hincrby('a', 'a1', 1), 1);
        // real logic
        $this->assertEquals($this->client->hincrby('a', 'a1', 1), 2);
        $this->assertEquals($this->client->hincrby('a', 'a1', 2), 4);
        // negative values decrement
        $this->assertEquals($this->client->hincrby('a', 'a1', -3), 1);
        // hash that exists, but key that doesn't
        $this->assertEquals($this->client->hincrby('a', 'a2', 3), 3);
        // finally a key that's not an int
        $this->client->hset('a', 'a3', 'foo');
        $this->assertEquals($this->client->hincrby('a', 'a3', 1), false);
    }

    public function test_hincrbyfloat()
    {
        $infos = $this->client->info();
        foreach ($infos as $info) {
            $version = $info['redis_version'];
            if (version_compare($version, "2.5.0", "<")) {
                $this->markTestSkipped();
            }
        }

        // key is not a hash
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->hincrbyfloat('a', 'a1', 1), false);
        $this->client->del('a');
        // no key should create the hash and incr the key's value to 1
        $this->assertEquals($this->client->hincrbyfloat('a', 'a1', 1), 1.0);
        $this->assertEquals($this->client->hincrbyfloat('a', 'a1', 1), 2.0);
        $this->assertEquals($this->client->hincrbyfloat('a', 'a1', 1.2), 3.2);
    }

    public function test_hkeys()
    {
        // key is not a hash
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->hkeys('a'), false);
        $this->client->del('a');
        // no key
        $this->assertEquals($this->client->hkeys('a'), array());
        // real logic
        $h = array('a1' => '1', 'a2' => '2', 'a3' => '3');
        $this->make_hash('a', $h);
        $keys = array_keys($h);
        sort($keys);
        $remote_keys = $this->client->hkeys('a');
        sort($remote_keys);
        $this->assertEquals($keys, $remote_keys);
    }

    public function test_hlen()
    {
        // key is not a hash
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->hlen('a'), false);
        $this->client->del('a');
        // no key
        $this->assertEquals($this->client->hlen('a'), 0);
        // real logic
        $this->make_hash('a', array('a1' => 1, 'a2' => 2, 'a3' => 3));
        $this->assertEquals($this->client->hlen('a'), 3);
        $this->client->hdel('a', 'a3');
        $this->assertEquals($this->client->hlen('a'), 2);
    }

    public function test_hvals()
    {
        // key is not a hash
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->hvals('a'), false);
        $this->client->del('a');
        // no key
        $this->assertEquals($this->client->hvals('a'), array());
        // real logic
        $h = array('a1' => '1', 'a2' => '2', 'a3' => '3');
        $this->make_hash('a', $h);
        $vals = array_values($h);
        sort($vals);
        $remote_vals = $this->client->hvals('a');
        sort($remote_vals);
        $this->assertEquals($vals, $remote_vals);
    }

    // SORT
    public function test_sort_bad_key()
    {
        //CLUSTER
        $this->markTestSkipped();

        // key is not set
        $this->assertEquals($this->client->sort('a'), array());
        // key is a string value
        $this->client->set('a', 'a');
        $this->assertEquals($this->client->sort('a'), false);
        $this->client->del('a');
    }

    public function test_sort_basic()
    {
        //CLUSTER
        $this->markTestSkipped();

        $this->make_list('a', '3214');
        $this->assertEquals($this->client->sort('a'), array('1', '2', '3', '4'));
    }

    public function test_sort_limited()
    {
        //CLUSTER
        $this->markTestSkipped();

        $this->make_list('a', '3214');
        $this->assertEquals($this->client->sort('a', array('limit' => array(1, 2))), array('2', '3'));
    }

    public function test_sort_by()
    {
        //CLUSTER
        $this->markTestSkipped();
        $this->client->set('score:1', 8);
        $this->client->set('score:2', 3);
        $this->client->set('score:3', 5);
        $this->make_list('a_values', '123');
        $this->assertEquals($this->client->sort('a_values', array('by'=>'score:*')), array('2', '3', '1'));
    }

    public function test_sort_get()
    {
        //CLUSTER
        $this->markTestSkipped();

        $this->client->set('user:1', 'u1');
        $this->client->set('user:2', 'u2');
        $this->client->set('user:3', 'u3');
        $this->make_list('a', '231');
        $this->assertEquals($this->client->sort('a', array('get'=>'user:*')), array('u1', 'u2', 'u3'));
    }

    public function test_sort_get_multi()
    {
        //CLUSTER
        $this->markTestSkipped();

        $this->client->set('user:1', 'u1');
        $this->client->set('user:2', 'u2');
        $this->client->set('user:3', 'u3');
        $this->make_list('a', '231');
        $this->assertEquals($this->client->sort('a', array('get'=> array('user:*', '//'))), array('u1', '1', 'u2', '2', 'u3', '3'));
    }

    public function test_sort_desc()
    {
        //CLUSTER
        $this->markTestSkipped();

        $this->make_list('a', '231');
        $this->assertEquals($this->client->sort('a', array('sort' => 'desc')), array('3', '2', '1'));
    }

    public function test_sort_alpha()
    {
        //CLUSTER
        $this->markTestSkipped();

        $this->make_list('a', 'ecbda');
        $this->assertEquals($this->client->sort('a', array('alpha' => true)), array('a', 'b', 'c', 'd', 'e'));
    }

    public function test_sort_store()
    {
        //CLUSTER
        $this->markTestSkipped();

        $this->make_list('a', '231');
        $this->assertEquals($this->client->sort('a', array('store' => 'sorted_values')), 3);
        $this->assertEquals($this->client->lrange('sorted_values', 0, 5), array('1', '2', '3'));
    }

    public function test_sort_all_options()
    {
        //CLUSTER
        $this->markTestSkipped();

        $this->client->set('user:1:username', 'zeus');
        $this->client->set('user:2:username', 'titan');
        $this->client->set('user:3:username', 'hermes');
        $this->client->set('user:4:username', 'hercules');
        $this->client->set('user:5:username', 'apollo');
        $this->client->set('user:6:username', 'athena');
        $this->client->set('user:7:username', 'hades');
        $this->client->set('user:8:username', 'dionysus');

        $this->client->set('user:1:favorite_drink', 'yuengling');
        $this->client->set('user:2:favorite_drink', 'rum');
        $this->client->set('user:3:favorite_drink', 'vodka');
        $this->client->set('user:4:favorite_drink', 'milk');
        $this->client->set('user:5:favorite_drink', 'pinot noir');
        $this->client->set('user:6:favorite_drink', 'water');
        $this->client->set('user:7:favorite_drink', 'gin');
        $this->client->set('user:8:favorite_drink', 'apple juice');

        $this->make_list('gods', '12345678');
        $num = $this->client->sort(
                        'gods',
                        array(
                                        'limit' => array(2, 4),
                                        'by' => 'user:*:username',
                                        'get' => 'user:*:favorite_drink',
                                        'sort' => 'desc',
                                        'alpha' => true,
                                        'store' => 'sorted'
                        )
        );
        $this->assertEquals($num, 4);
        $this->assertEquals($this->client->lrange('sorted', 0, 10), array('vodka', 'milk', 'gin', 'apple juice'));
    }

    public function test_strict_zadd()
    {
        $client = $this->get_client();
        $client->zadd('a', 1.0, 'a1');
        $client->zadd('a', 2.0, 'a2');
        $client->zadd('a', 3.0, 'a3');
        $this->assertEquals($client->zrange('a', 0, 3, true), array('a1' => 1.0, 'a2' => 2.0, 'a3' => 3.0));
    }

    public function test_strict_lrem()
    {
        $client = $this->get_client();
        $client->rpush('a', 'a1');
        $client->rpush('a', 'a2');
        $client->rpush('a', 'a3');
        $client->rpush('a', 'a1');
        $client->lrem('a', 'a1', 0);
        $this->assertEquals($client->lrange('a', 0, -1), array('a2', 'a3'));
    }

    public function test_strict_setex()
    {
        $client = $this->get_client();
        $this->assertEquals($client->setex('a', 60, '1'), true);
        $this->assertEquals($client->get('a'), '1');
        $this->assertEquals($client->ttl('a'), 60);
    }

    public function test_strict_expire()
    {
        $client = $this->get_client();
        $this->assertEquals($client->expire('a', 10), false);
        $client->set('a', 'foo');
        $this->assertEquals($client->expire('a', 10), true);
        $this->assertEquals($client->ttl('a'), 10);
        $this->assertEquals($client->persist('a'), true);
        $this->assertEquals($client->ttl('a'), -1);
    }

    //// BINARY SAFE
    // TODO add more tests
    public function test_binary_get_set()
    {
        $this->assertTrue($this->client->set(' foo bar ', '123'));
        $this->assertEquals($this->client->get(' foo bar '), '123');

        $this->assertTrue($this->client->set(' foo\r\nbar\r\n ', '456'));
        $this->assertEquals($this->client->get(' foo\r\nbar\r\n '), '456');

        $this->assertTrue($this->client->set(' \r\n\t\x07\x13 ', '789'));
        $this->assertEquals($this->client->get(' \r\n\t\x07\x13 '), '789');

        $this->assertEquals(1, $this->client->del(' foo bar '));
        $this->assertEquals(1, $this->client->del(' foo\r\nbar\r\n '));
        $this->assertEquals(1, $this->client->del(' \r\n\t\x07\x13 '));
    }

    public function test_binary_lists()
    {
        $mapping = array(
                        'foo bar' => array('1', '2', '3'),
                        'foo\r\nbar\r\n'=> array('4', '5', '6'),
                        'foo\tbar\x07' => array('7', '8', '9'),
        );
        // fill in lists
        foreach ($mapping as $key => $value) {
            $n = 0;
            foreach ($value as $c) {
                $this->assertEquals($this->client->rpush($key, $c), ++$n);
            }
        }

        // check that KEYS returns all the keys as they are
        //$this->assertEquals(sorted($this->client->keys('*')), sorted(dictkeys(mapping)))

        // check that it is possible to get list content by key name
        foreach ($mapping as $key => $value) {
            $this->assertEquals($this->client->lrange($key, 0, -1), $value);
        }
    }

    public function test_large_responses()
    {
        $this->markTestSkipped();
        // load up 5MB of data into a key
        $data = '';
        $ascii_letters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $range = range(1, 5000000);
        foreach ($range as $i) {// len(ascii_letters
            $data .= $ascii_letters;
        }
        $this->client->set('a', $data);
        $this->assertEquals($this->client->get('a'), $data);
    }

}
