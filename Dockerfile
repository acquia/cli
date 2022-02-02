FROM alpine

RUN apk add \
  curl \
  php \
  php-curl \
  php-json \
  php-mbstring \
  php-phar \
  php-xml \
  && curl https://github.com/acquia/cli/releases/latest/download/acli.phar -L -o /usr/local/bin/acli \
  && chmod +x /usr/local/bin/acli
