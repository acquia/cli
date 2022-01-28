FROM alpine

ARG ACLI_CHECKSUM="ec6368888922d4d036917b033261920423352c4c40d702c00a316a6553263e7c"
ARG ACLI_VERSION="1.22.0"

RUN apk add \
  curl \
  php \
  php-curl \
  php-json \
  php-mbstring \
  php-phar \
  php-xml \
  && curl https://github.com/acquia/cli/releases/download/${ACLI_VERSION}/acli.phar -L -o /usr/local/bin/acli \
  && [ "${ACLI_CHECKSUM}" = "$(sha256sum /usr/local/bin/acli | cut -d ' ' -f1)" ] \
  && chmod +x /usr/local/bin/acli
