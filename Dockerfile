FROM node:20-bookworm-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    unzip \
    php-cli php-json php-mbstring php-xml php-curl php-zip php-pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /opt/render/project/src

COPY package.json package-lock.json* ./
RUN npm ci --omit=dev

COPY composer.json composer.lock* ./
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --quiet && rm composer-setup.php \
    && php composer.phar install --no-dev --no-scripts \
    && rm composer.phar

COPY . .

ENV NODE_ENV=production
EXPOSE 3000

CMD ["node", "start.js"]
