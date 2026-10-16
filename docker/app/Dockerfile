FROM au9500/php:8.5

# ext-apcu for ApcuMetricsStorage (not in the base image);
# apc.enable_cli=1 so APCu also works in PHPUnit / CLI runs
RUN pecl install apcu \
    && docker-php-ext-enable apcu \
    && echo "apc.enable_cli=1" >> "$PHP_INI_DIR/conf.d/docker-php-ext-apcu.ini"

WORKDIR /var/www/html

CMD ["/usr/local/bin/container-start.sh"]
