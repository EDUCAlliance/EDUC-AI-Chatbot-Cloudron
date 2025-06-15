FROM cloudron/base:4.2.0@sha256:46da2fffb36353ef714f97ae8e962bd2c212ca091108d768ba473078319a47f4

# Install Git, Supervisor, PostgreSQL client, and additional tools
RUN apt-get update && apt-get install -y \
    git \
    supervisor \
    postgresql-client \
    curl \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

ENV GIT_CONFIG_GLOBAL=/app/code/public/.gitconfig

RUN mkdir -p /app/data /app/code/apps /app/code/admin /app/code/assets
WORKDIR /app/code

# Configure Apache for Cloudron (following official documentation)
RUN rm /etc/apache2/sites-enabled/* \
    && sed -e 's,^ErrorLog.*,ErrorLog "/dev/stderr",' -i /etc/apache2/apache2.conf \
    && sed -e "s,MaxSpareServers[^:].*,MaxSpareServers 5," -i /etc/apache2/mods-available/mpm_prefork.conf \
    && a2disconf other-vhosts-access-log \
    && echo "Listen 8000" > /etc/apache2/ports.conf

COPY apache/app.conf /etc/apache2/sites-enabled/app.conf

# Set proper ServerName to avoid warnings
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Fix PHP sessions for read-only filesystem
RUN rm -rf /var/lib/php/sessions && ln -s /run/php/sessions /var/lib/php/sessions

# Enable Apache modules
RUN a2enmod rewrite
RUN a2enmod headers

# Configure PHP
RUN a2enmod php8.1
RUN apt-get update && apt-get install -y \
    php8.1-pgsql \
    php8.1-curl \
    php8.1-mbstring \
    php8.1-xml \
    php8.1-zip \
    php8.1-gd \
    && rm -rf /var/lib/apt/lists/*

RUN crudini --set /etc/php/8.1/apache2/php.ini PHP upload_max_filesize 256M && \
    crudini --set /etc/php/8.1/apache2/php.ini PHP upload_max_size 256M && \
    crudini --set /etc/php/8.1/apache2/php.ini PHP post_max_size 256M && \
    crudini --set /etc/php/8.1/apache2/php.ini PHP memory_limit 512M && \
    crudini --set /etc/php/8.1/apache2/php.ini PHP max_execution_time 300 && \
    crudini --set /etc/php/8.1/apache2/php.ini Session session.save_path /run/app/sessions && \
    crudini --set /etc/php/8.1/apache2/php.ini Session session.gc_probability 1 && \
    crudini --set /etc/php/8.1/apache2/php.ini Session session.gc_divisor 100

# Copy application files
COPY public/ /app/code/public/
COPY admin/ /app/code/admin/
COPY assets/ /app/code/assets/
COPY scripts/ /app/code/scripts/
COPY start.sh /app/code/
COPY .gitignore /app/code/public/

# Ensure directories and permissions are set correctly
RUN mkdir -p /app/code/admin/ajax \
    && chmod +x /app/code/scripts/deploy-background.php

# Set permissions
RUN chown -R www-data.www-data /app/code/
RUN chown -R www-data.www-data /app/data/
RUN chmod +x /app/code/start.sh

CMD [ "/app/code/start.sh" ]
