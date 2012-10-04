wget -O phpredis.tar.gz --no-check-certificate https://github.com/nicolasff/phpredis/tarball/master
tar -xzf phpredis.tar.gz
sh -c "cd nicolasff-phpredis-* && phpize && ./configure && make && sudo make install"
echo "extension=redis.so" >> `php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||"`
phpenv rehash
php -m
wget http://getcomposer.org/composer.phar
php composer.phar install
