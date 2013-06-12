sh -c "`which redis-server` $CI_HOME/bin/redis/redis-node-1.conf --dir ${CI_HOME}/bin/redis --include ${CI_HOME}/bin/redis/redis-common.conf"
sh -c "`which redis-server` $CI_HOME/bin/redis/redis-node-2.conf --dir ${CI_HOME}/bin/redis --include ${CI_HOME}/bin/redis/redis-common.conf"
sh -c "`which redis-server` $CI_HOME/bin/redis/redis-node-5.conf --dir ${CI_HOME}/bin/redis --include ${CI_HOME}/bin/redis/redis-common.conf"
sh -c "`which redis-server` $CI_HOME/bin/redis/redis-node-6.conf --dir ${CI_HOME}/bin/redis --include ${CI_HOME}/bin/redis/redis-common.conf"
wget -O phpredis.tar.gz --no-check-certificate https://github.com/nicolasff/phpredis/tarball/master
tar -xzf phpredis.tar.gz
sh -c "cd nicolasff-phpredis-* && phpize && ./configure && make && sudo make install"
echo "extension=redis.so" >> `php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||"`
wget http://getcomposer.org/composer.phar
php composer.phar install
