[u393436395@us-phx-web560 public_html]$ ls /usr/bin/php*
/usr/bin/php  /usr/bin/php-cgi  /usr/bin/phpize
[u393436395@us-phx-web560 public_html]$ /usr/bin/php83 -v
-bash: /usr/bin/php83: No such file or directory
[u393436395@us-phx-web560 public_html]$








2:44 AM






/usr/bin/php83 is not installed on that server path. On Hostinger/shared hosting, PHP 8.3 CLI is often somewhere else.
Run these checks:
ls /opt/alt/
ls /opt/alt/php*/usr/bin/php
Then try any PHP 8.3 path you find, for example:
/opt/alt/php83/usr/bin/php -v
or sometimes:
/opt/alt/php83/usr/bin/php artisan key:generate
If /opt/alt/php83/usr/bin/php exists, make your shell use it:
alias php=/opt/alt/php83/usr/bin/php
php -v
Permanent:
echo "alias php=/opt/alt/php83/usr/bin/php" >> ~/.bashrc
source ~/.bashrc
If ls /opt/alt/php*/usr/bin/php shows no PHP 8.3, then PHP 8.3 CLI is not available on the server. You’ll need to enable PHP 8.3 from the hosting control panel or ask hosting support to enable PHP 8.3 CLI for your account.

2:44 AM






[u393436395@us-phx-web560 public_html]$ ls /opt/alt/
alt-ImageMagick  clos_ssa       libde265  libraqm     openssl11  php70          php81         postgresql11  ruby33
alt-nodejs18     curlssl11      libgd     libssh211   pcre802    php71          php82         python27      sqlite
alt-nodejs20     curlssl30      libharu   libwebp     php52      php72          php83         python311     suexecs
alt-nodejs22     cyrus-sasl     libheif   libxml2     php53      php73          php84         python37      tests
alt-nodejs24     dav1d          libicu57  net-snmp11  php54      php74          php85         python38      vips
alt-php-config   krb5           libicu71  openldap11  php55      php74-imunify  php-internal  redis
aom              libc-client11  liblqr-1  openssl     php56      php80          php-xray      ruby22
[u393436395@us-phx-web560 public_html]$




2:44 AM






Great, PHP 8.3 exists. Use this path:
/opt/alt/php83/usr/bin/php -v
If it shows PHP 8.3, run Laravel commands like this:
/opt/alt/php83/usr/bin/php artisan key:generate
/opt/alt/php83/usr/bin/php artisan migrate --force
/opt/alt/php83/usr/bin/php artisan storage:link
/opt/alt/php83/usr/bin/php artisan config:cache
/opt/alt/php83/usr/bin/php artisan route:cache
/opt/alt/php83/usr/bin/php artisan view:cache
To make php mean PHP 8.3 in your shell:
alias php=/opt/alt/php83/usr/bin/php
php -v
To make it permanent:
echo "alias php=/opt/alt/php83/usr/bin/php" >> ~/.bashrc
source ~/.bashrc
php -v
For Composer, if it still uses PHP 8.1, run Composer through PHP 8.3:
/opt/alt/php83/usr/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader
If /usr/local/bin/composer is different, check:
which composer

2:45 AM






 2026_06_26_000001_create_pos_sessions_table ............................................................... 29.43ms DONE
  2026_06_26_000002_create_expenses_tables .................................................................. 15.53ms DONE
  2026_06_26_000003_track_purchase_receipts_and_sales_returns ............................................... 43.90ms DONE
  2026_06_26_000004_add_unit_cost_to_sale_items .............................................................. 4.42ms DONE


   Error

  Call to undefined function Illuminate\Filesystem\exec()

  at vendor/laravel/framework/src/Illuminate/Filesystem/Filesystem.php:358
    354▕         if (! windows_os()) {
    355▕             if (function_exists('symlink')) {
    356▕                 return symlink($target, $link);
    357▕             } else {
  ➜ 358▕                 return exec('ln -s '.escapeshellarg($target).' '.escapeshellarg($link)) !== false;
    359▕             }
    360▕         }
    361▕
    362▕         $mode = $this->isDirectory($target) ? 'J' : 'H';

      +14 vendor frames

  15  artisan:16
      Illuminate\Foundation\Application::handleCommand()


   INFO  Configuration cached successfully.


   INFO  Routes cached successfully.

what is this 


Show more

2:45 AM






This happened during:
php artisan storage:link
Laravel tried to create the symlink:
public/storage -> storage/app/public
But your hosting server has disabled both:
symlink()
exec()
So Laravel could not create the storage link.
Good news: migrations, config cache, and route cache worked. This error is only about public file storage.
What To Do
First check if the link already exists:
ls -la public/storage
If it exists, ignore the error.
If it does not exist, ask hosting support to enable symlink, or create it from hosting file manager if it supports “create symlink”.
The target should be:
public/storage
linked to:
../storage/app/public
If symlink is impossible on this hosting, we can use a fallback: change Laravel’s public disk path to store uploads directly inside public/storage instead of storage/app/public. That avoids symlink completely. This is common on restricted shared hosting.
Also, because config:cache and route:cache succeeded, now run:
php artisan view:cache
Then test the site. If only uploaded logos/images fail, we’ll apply the no-symlink storage fallback.

2:46 AM






Illuminate\Foundation\ViteManifestNotFoundException
vendor/laravel/framework/src/Illuminate/Foundation/Vite.php:960
Vite manifest not found at: /home/u393436395/domains/system.rainshield.lk/public_html/public/build/manifest.json
now isee this is this becaue build missing




2:52 AM






Yes, exactly. Laravel is looking for the Vite production build file here:
public/build/manifest.json
That file is created by:
npm run build
Fix On Server
From public_html, run:
npm install
npm run build
If Node is not available, check:
node -v
npm -v
On your server I saw Node versions under /opt/alt/, so if normal npm does not work, try:
/opt/alt/alt-nodejs22/root/usr/bin/node -v
/opt/alt/alt-nodejs22/root/usr/bin/npm -v
Then build with:
/opt/alt/alt-nodejs22/root/usr/bin/npm install
/opt/alt/alt-nodejs22/root/usr/bin/npm run build
After build, confirm this file exists:
ls -la public/build/manifest.json
Alternative
Since I already ran npm run build locally successfully, you can also upload the local public/build folder to the server at:
/home/u393436395/domains/system.rainshield.lk/public_html/public/build
But best is to run npm run build on the server if Node works.

2:52 AM






[u393436395@us-phx-web560 public_html]$ npm install
npm run build
-bash: npm: command not found
-bash: npm: command not found
[u393436395@us-phx-web560 public_html]$




2:53 AM






npm is not in the default shell path. Your server has Node installed under /opt/alt/, so use the full path.
Try this first:
/opt/alt/alt-nodejs22/root/usr/bin/node -v
/opt/alt/alt-nodejs22/root/usr/bin/npm -v
If both show versions, run:
/opt/alt/alt-nodejs22/root/usr/bin/npm install
/opt/alt/alt-nodejs22/root/usr/bin/npm run build
To make node and npm available in this shell:
export PATH=/opt/alt/alt-nodejs22/root/usr/bin:$PATH
node -v
npm -v
Then:
npm install
npm run build
Permanent:
echo 'export PATH=/opt/alt/alt-nodejs22/root/usr/bin:$PATH' >> ~/.bashrc
source ~/.bashrc
After build, check:
ls -la public/build/manifest.json
If Node 22 has issues, use Node 20:
export PATH=/opt/alt/alt-nodejs20/root/usr/bin:$PATH
npm install
npm run build

2:53 AM






[u393436395@us-phx-web560 public_html]$ /opt/alt/alt-nodejs22/root/usr/bin/node -v
/opt/alt/alt-nodejs22/root/usr/bin/npm -v
v22.18.0
10.9.3
[u393436395@us-phx-web560 public_html]$




2:53 AM






Good, Node/NPM is available. Now run:
export PATH=/opt/alt/alt-nodejs22/root/usr/bin:$PATH
npm install
npm run build
Then confirm:
ls -la public/build/manifest.json
If build succeeds, refresh the site. The Vite manifest error should be gone.

2:54 AM