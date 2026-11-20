FROM au9500/php:8.5

# ext-apcu for ApcuMetricsStorage (not in the base image);
# apc.enable_cli=1 so APCu also works in PHPUnit / CLI runs
COPY --from=ghcr.io/php/pie:1.5.5-bin /pie /usr/bin/pie
RUN pie install apcu/apcu \
    && echo "apc.enable_cli=1" > "$PHP_INI_DIR/conf.d/99-apcu-cli.ini"

WORKDIR /var/www/html

CMD ["/usr/local/bin/container-start.sh"]
