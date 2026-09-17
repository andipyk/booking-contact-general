# Shared base image for every wp-portfolio project.
#
# Canonical copy: ~/.claude/skills/wp-portfolio/references/docker-starter/.
# Every project vendors this directory byte-for-byte and tags the result
# `wp-portfolio-wordpress:7.1-php8.4`, so Docker stores one image for all of
# them. Project-specific settings belong in compose.yaml, never here: an edit
# in one project would silently change the image the others run on.
#
# The tag has no user namespace on purpose. An unqualified name resolves to
# Docker Hub's official `library/` namespace, which nobody else can publish
# to, so Compose's pull-before-build attempt fails harmlessly instead of
# fetching a stranger's image.
#
# What it adds to the official image:
#   - phpredis, so the object cache runs on the extension, not Predis
#   - WP-CLI, so the CLI service runs this same image as the same www-data
#     (UID 33) with the same PHP and extensions as web requests
#   - the MariaDB client, which `wp db export/import/query` shell out to
ARG WORDPRESS_IMAGE=wordpress:7.1-php8.4-apache
FROM ${WORDPRESS_IMAGE}

ARG PHPREDIS_VERSION=6.3.0
ARG WPCLI_VERSION=2.12.0
ARG WPCLI_SHA512=be928f6b8ca1e8dfb9d2f4b75a13aa4aee0896f8a9a0a1c45cd5d2c98605e6172e6d014dda2e27f88c98befc16c040cbb2bd1bfa121510ea5cdf5f6a30fe8832

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends less mariadb-client $PHPIZE_DEPS; \
    pecl install "redis-${PHPREDIS_VERSION}"; \
    docker-php-ext-enable redis; \
    apt-get purge -y --auto-remove $PHPIZE_DEPS; \
    rm -rf /var/lib/apt/lists/* /tmp/pear

RUN set -eux; \
    curl -fsSL -o /usr/local/bin/wp \
      "https://github.com/wp-cli/wp-cli/releases/download/v${WPCLI_VERSION}/wp-cli-${WPCLI_VERSION}.phar"; \
    echo "${WPCLI_SHA512}  /usr/local/bin/wp" | sha512sum -c -; \
    chmod +x /usr/local/bin/wp; \
    install -d -o www-data -g www-data /var/www/.wp-cli/cache; \
    printf 'apache_modules:\n  - mod_rewrite\n' > /var/www/.wp-cli/config.yml

# apache_modules tells `wp rewrite flush --hard` that mod_rewrite is loaded;
# without it WP-CLI refuses to write .htaccess and prints a warning instead.
ENV WP_CLI_CACHE_DIR=/var/www/.wp-cli/cache \
    WP_CLI_CONFIG_PATH=/var/www/.wp-cli/config.yml

COPY php.ini /usr/local/etc/php/conf.d/zz-wp-portfolio.ini

LABEL org.opencontainers.image.title="wp-portfolio-wordpress" \
      org.opencontainers.image.description="WordPress 7.1 / PHP 8.4 / Apache with phpredis and WP-CLI for local wp-portfolio stacks"
