FROM php:8-alpine

RUN set -eux ; \
  apk add --no-cache \
    tini

RUN curl https://github.com/acquia/cli/releases/latest/download/acli.phar -L -o /usr/local/bin/acli \
  && chmod +x /usr/local/bin/acli

COPY docker-entrypoint.sh /docker-entrypoint.sh

ENTRYPOINT ["/docker-entrypoint.sh"]

CMD ["acli"]
