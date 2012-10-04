wget --no-check-certificate https://github.com/nicolasff/phpredis/tarball/master
tar -xzf master
sh -c "cd nicolasff-phpredis-* && phpize && ./configure && make && sudo make install"
echo "extension=redis.so" >> `php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||"`
wget http://getcomposer.org/composer.phar
php composer.phar install
