H0st!nger-Temp-2026#03

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/?public/
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>




cd ~/domains/system.rainshield.lk/public_html
ln -s "$(pwd)/storage/app/public" "$(pwd)/public/storage"
ls -la public/storage


chmod -R 755 storage/app/public
ls -la storage/app/public/brands/1/

alias php=/opt/alt/php83/usr/bin/php
php -v

Node
/opt/alt/alt-nodejs22/root/usr/bin/node -v
/opt/alt/alt-nodejs22/root/usr/bin/npm -v


export PATH=/opt/alt/alt-nodejs22/root/usr/bin:$PATH
npm install
npm run build

ls -la public/build/manifest.json