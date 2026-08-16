FROM dunglas/frankenphp:php8.4

WORKDIR /app

RUN install-php-extensions \
    redis \
    pdo \
    pdo_pgsql \
    zip \
    intl \
    gd \
    pcntl \
    posix

RUN curl -fsSL https://deb.nodesource.com/setup_lts.x | bash - \
    && apt-get update && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd -g 1000 app \
    && useradd -u 1000 -ms /bin/bash -g app app \
    && mkdir -p /data /config \
    && chown -R app:app /data /config

COPY --chown=app:app . /app

USER app

EXPOSE 80

CMD ["php", "artisan", "octane:frankenphp", "--workers=auto", "--max-requests=250"]
