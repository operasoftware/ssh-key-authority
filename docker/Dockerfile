FROM alpine:3.8
LABEL maintainer="Marc Mettke <marc@itmettke.de>"

ENV SYSTEM https://github.com/operasoftware/ssh-key-authority.git
ADD entrypoint.sh /entrypoint.sh
ADD healthcheck.sh /healthcheck.sh
ADD cron /var/spool/cron/crontabs/root

RUN mkdir -p /var/log/keys/ /run/php/ /ska/ && \
    adduser --system --disabled-password keys-sync && \
    apk add openssh \ 
            php \
            php-fpm \
            php7-json \
            php7-ldap \
            php7-mbstring \
            php7-mysqli \
            php7-pcntl \
            php7-posix \
            php7-ssh2 \
            rsync \
            ssmtp \
            sudo && \
    sed -i -e '/listen =/ s/= .*/= 0.0.0.0:9000/' /etc/php7/php-fpm.d/www.conf && \
    sed -i -e '/;pid =/ s/.*/pid = \/var\/run\/php-fpm.pid/' /etc/php7/php-fpm.conf && \
    echo "" >> /etc/php7/php-fpm.conf && \
    chmod +x /entrypoint.sh /healthcheck.sh && \
    ln -sf /dev/stderr /var/log/php7/error.log
RUN apk add git && \
    git clone ${SYSTEM} /ska && \
    apk del git && \
    chown -R keys-sync:nogroup /ska/config

EXPOSE 9000
VOLUME /ska/config
VOLUME /public_html

ENTRYPOINT "/entrypoint.sh"
HEALTHCHECK CMD /healcheck.sh
