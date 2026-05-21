FROM serversideup/php:8.4-fpm-nginx

USER root

# Repos extras: PostgreSQL 17 (PGDG) + Node 22 (NodeSource)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        acl \
        libpng-dev \
        libjpeg-dev \
        libwebp-dev \
        libfreetype6-dev \
        curl ca-certificates gnupg \
        python3 \
    && install -d /usr/share/postgresql-common/pgdg \
    && curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc \
        -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc \
    && echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt trixie-pgdg main" \
        > /etc/apt/sources.list.d/pgdg.list \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends postgresql-client-17 nodejs \
    && rm -rf /var/lib/apt/lists/*

# ADR-010: umask 002 — arquivos criados por PHP-FPM/queue/CLI nascem 664/775,
# permitindo que usuarios humanos no host (membros do grupo www-data) editem
# sem precisar de sudo chown.
RUN echo 'umask 002' > /etc/profile.d/umask.sh \
    && chmod 0644 /etc/profile.d/umask.sh \
    && echo 'umask 002' >> /etc/bash.bashrc

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd exif \
    && pecl install pcov \
    && docker-php-ext-enable pcov

RUN git config --global --add safe.directory '*'

USER www-data
