#!/bin/bash
#chmod +x before start !

# Settings you will have to adapt to your environment
BASE_PROTOCOL=http
INSTANCE_NAME=gogocarto
WEB_DIR=/var/www/gogocarto
WEB_URL=lednsdusiteici
CONTACT_EMAIL=contact@gogocarto.local # default email contact
USE_AS_SAAS=false # true = allow to create a farm of map, false = single map
BASE_PATH='' # base path on webserver
SECRET=`head -c 32 /dev/random | base64` # randomly generated string
MAILER_TRANSPORT=smtp # email transport protocol
MAILER_HOST=127.0.0.1 # email server host
MAILER_USER=smtp@monserveur.fr # email sender user
MAILER_PASSWORD=null # email sender password
CSRF_PROTECTION=false # CSRF protection for forms
OAUTH_COMMUNS_ID=disabled # oauth id for https://login.lescommuns.org
OAUTH_COMMUNS_SECRET=disabled # oauth secret for https://login.lescommuns.org
OAUTH_GOOGLE_ID=disabled # oauth id for google
OAUTH_GOOGLE_SECRET=disabled # oauth secret for google
OAUTH_FACEBOOK_ID=disabled # oauth id for facebook
OAUTH_FACEBOOK_SECRET=disabled # oauth secret for facebook

apt update -y ;
apt dist-upgrade -y ;
apt install -y \
sudo \
curl \
build-essential \
git \
zip \
unzip \
php7.0-fpm \
php7.0 \
php7.0-cli \
php7.0-curl \
php7.0-dev \
php7.0-gd \
php7.0-bcmath \
php-mongodb \
php7.0-mbstring \
php7.0-zip \
nginx \
git-core \
mongodb \
openssl \
libsasl2-dev \
libssl-dev \
ssl-cert;
apt-get autoclean -y;

php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('sha384', 'composer-setup.php') === '48e3236262b34d30969dca3c37281b3b4bbe3221bda826ac6a9a62d6444cdb0dcd0615698a5cbe587c3f0fe57a54d8f5') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php --install-dir=/usr/local/bin
php -r "unlink('composer-setup.php');"


curl -sL https://deb.nodesource.com/setup_8.x | sudo -E bash -
apt-get install -y nodejs
curl -L https://npmjs.org/install.sh | sudo sh

git clone https://gitlab.adullact.net/pixelhumain/GoGoCarto /var/www/gogocarto
cd /var/www/gogocarto
mkdir web/uploads
chmod 777 -R web/uploads /var 

npm install gulp -g
npm install

echo "parameters:
  use_as_saas: ${USE_AS_SAAS}
  base_protocol: ${BASE_PROTOCOL}
  instance_name: ${INSTANCE_NAME}
  base_url: ${WEB_URL}
  contact_email: ${CONTACT_EMAIL}
  base_path: ${BASE_PATH}
  mailer_transport: ${MAILER_TRANSPORT}
  mailer_host: ${MAILER_HOST}
  mailer_user: ${MAILER_USER}
  mailer_password: ${MAILER_PASSWORD}
  secret: ${SECRET}
  csrf_protection: ${CSRF_PROTECTION}
  oauth_communs_id: ${OAUTH_COMMUNS_ID}
  oauth_communs_secret: ${OAUTH_COMMUNS_SECRET}
  oauth_google_id: ${OAUTH_GOOGLE_ID}
  oauth_google_secret: ${OAUTH_GOOGLE_SECRET}
  oauth_facebook_id: ${OAUTH_FACEBOOK_ID}
  oauth_facebook_secret: ${OAUTH_FACEBOOK_SECRET}
" > app/config/parameters.yml

composer.phar config "platform.ext-mongo" "1.6.16" && composer.phar require alcaeus/mongo-php-adapter
composer.phar install
bin/console assets:install --symlink web  -n

gulp build
gulp production

bin/console doctrine:mongodb:schema:create  -n
bin/console doctrine:mongodb:generate:hydrators -n
bin/console doctrine:mongodb:generate:proxies  -n
bin/console doctrine:mongodb:fixtures:load -n

echo "server {
  listen 80;
  listen [::]:80;
  server_name *.${WEB_URL};

  root ${WEB_DIR}/web;

    # Enable compression for JS/CSS/HTML bundle, for improved client load times.
  # It might be nice to compress JSON, but leaving that out to protect against potential
  # compression+encryption information leak attacks like BREACH.
  gzip on;
  gzip_types text/css text/html application/javascript;
  gzip_vary on;

    access_log /var/log/nginx/${WEB_URL}.access.log;
  error_log /var/log/nginx/${WEB_URL}.error.log;
 

    # cache.appcache, your document html and data
    location ~* \.(?:manifest|appcache|html?|xml|json)$ {
    add_header Cache-Control \"max-age=0\";
    }

    # Feed
    location ~* \.(?:rss|atom)$ {
    add_header Cache-Control \"max-age=3600\";
    }

    # Media: images, icons, video, audio, HTC
    location ~* \.(?:jpg|jpeg|gif|png|ico|cur|gz|svg|mp4|ogg|ogv|webm|htc)$ {
    access_log off;
    add_header Cache-Control \"max-age=2592000\";
    }

    # Media: svgz files are already compressed.
    location ~* \.svgz$ {
    access_log off;
    gzip off;
    add_header Cache-Control \"max-age=2592000\";
    }

    # CSS and Javascript
    location ~* \.(?:css|js)$ {
    add_header Cache-Control \"max-age=31536000\";
    access_log off;
    }

    # WebFonts
  location ~* \.(?:ttf|ttc|otf|eot|woff|woff2)$ {
    add_header Cache-Control \"max-age=2592000\";
    access_log off;
  }

  # strip app.php/ prefix if it is present
  rewrite ^/app\.php/?(.*)$ /\$1 permanent;

  location / {
    index app.php;
    try_files \$uri @rewriteapp;
  }

  location @rewriteapp {
    rewrite ^(.*)$ /app.php/\$1 last;
  }

  # pass the PHP scripts to FastCGI server from upstream phpfcgi
  location ~ ^/(app|app_dev|config)\.php(/|$) {
    fastcgi_pass unix:/var/run/php/php7.0-fpm.sock;
    fastcgi_split_path_info ^(.+\.php)(/.*)$;
    include fastcgi_params;
    fastcgi_param  SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    fastcgi_param  HTTPS off;
  }
}" > /etc/nginx/sites-available/${WEB_URL}

chown -R www-data:www-data /var/www/gogocarto
ln -nsf /etc/nginx/sites-available/${WEB_URL} /etc/nginx/sites-enabled/${WEB_URL}
nginx -t && service nginx restart


#admin account
#visit http://monsite.amoi/app_dev.php/_console
#doctrine:mongodb:schema:create
#doctrine:mongodb:generate:hydrators
#doctrine:mongodb:generate:proxies
#doctrine:mongodb:fixtures:load
#http://monsite.amoi/app_dev.php/project/initialize
#assetic:dump
#gulp build
#gulp production
#chown -R www-data:www-data /var/www/gogocarto
#cache:clear --env=prod --no-warmup 
#modifier le fichier app_dev.php et remplacer * par l'ip autorisée pour le debug
