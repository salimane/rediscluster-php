sh -c "/usr/bin/redis-server /home/travis/builds/salimane/rediscluster-php/bin/redis/redis-node-1.conf"
sh -c "/usr/bin/redis-server /home/travis/builds/salimane/rediscluster-php/bin/redis/redis-node-2.conf"
sh -c "/usr/bin/redis-server /home/travis/builds/salimane/rediscluster-php/bin/redis/redis-node-5.conf"
sh -c "/usr/bin/redis-server /home/travis/builds/salimane/rediscluster-php/bin/redis/redis-node-6.conf"
wget -O phpredis.tar.gz --no-check-certificate https://github.com/nicolasff/phpredis/tarball/master
tar -xzf phpredis.tar.gz
sh -c "cd nicolasff-phpredis-* && phpize && ./configure && make && sudo make install"
echo "extension=redis.so" >> `php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||"`
phpenv rehash
ps aux | grep redis-server
cat /etc/redis/redis.conf
wget http://getcomposer.org/composer.phar
php composer.phar install
