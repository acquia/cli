FROM alpine

ARG ACLI_CHECKSUM="3672a525f6c64e837bef9f7e38c7a4d9777447b792449ee6fdd4fcb632f6c0e2"
ARG ACLI_VERSION="1.21.1"

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
