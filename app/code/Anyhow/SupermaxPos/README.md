## Supermax Magento POS

## How to Install

                                  INSTALLATION INFORMATION(STEP) FOR SUPERMAX POS
                                     			(Version 2.3, 2.4)
                    --------------------------------------------------------------------------------

Manual Installation:

1. Download Supermax Magento Point Of Sale zip from Magento Marketplace Account or Anyhow Store Account and extract the zip.

2. After Extract create a folder named "code" under the app folder.

3. Then go to "code" folder and create folder named "Anyhow".

4. Then go to "Anyhow" folder and create folder named "SupermaxPos". And transfer all the files to the "SupermaxPos" folder.

5. move "supermaxpos" folder to your magento root directory and delete from the "SupermaxPos" path.

6. After successfully uploading run these below commands one after on completion, on your magento root directory by system terminal.

Command 1: php bin/magento module:enable Anyhow_SupermaxPos

Command 2: php bin/magento setup:upgrade

Command 3: php bin/magento cache:flush

Command 4: php bin/magento setup:static-content:deploy -f

7. Follow "Supermax POS Terminal Installation" process below to get the pos folder. After getting pos terminal build files upload the files to your pos root directory on which your pos terminal will open.

Note: Supermax pos terminal must be deployed on a subdomain, create a sub-domain like pos.example.com and upload pos terminal build files to the subdomain root.

Sub-domain must have https enabled if magento installed store have https enabled.

8. If you have language other that magento default english then follow "Language File Creation" below.


Supermax POS Terminal Installation
----------------------------------------------------

Order ID (ex: 565656)
Magento installed store link (ex: https://example.com) (test or live)

Supermax POS Terminal build will be given for the above shared Magento installed store link.

IMPORTANT NOTE: As Supermax POS License is for one Magento installed domain only (subdomains and subfolders act as separate website), the supermax POS terminal works for the given Magento store only. And you can change the store link for one time only from test to live. Magento test site link should look like a test site ex: test.example.com or example.com/test other than it will not be considered as a live site domain.

Note: Supermax POS Terminal must be run on a subdomain. So, create a subdomain like pos.example.com and upload pos terminal files to the subdomain root.

Magento test site link should look like a test site ex: test.example.com or example.com/test other than it will not be considered as live site domain.


Language File Creation
----------------------------------------------------

1. Go to assets/language in pos terminal root directory

2. Create a file for your required languages. File name must be your languagecode.json. Ex: for english language code is en-gb so file name is en-gb.json. 

Language code which is set for the language in admin end i.e in localization->languages.

3. Copy all the code of default en-gb.json file to required language file and save the file.

4. Then translate as per language.

Translation process:
*********************** 

5. You can see in language file like this "text_customer": "Customer". It's call a parameter.

you just need to translate the right part of the ":"

Ex: For German Language: "text_customer": "Klant"

Note: each language parameter separated by comma "," and for end parameter there is no need comma ","


Server End Configuration
----------------------------------------------------
Note:- Server end configuration is not covered in software license as well as in support. Below is the hint which will help you to configure your server.

1. NGINX

    location /pos/ {
        index index.html;
		location ~ ^(.*)$ { }
			location /pos/ {
  				rewrite ^/(.*) /index.html break;
		}
    }

2. Apache

    use .htaccess

    <IfModule mod_rewrite.c>
        RewriteEngine On

        # -- REDIRECTION to https (optional):
        # If you need this, uncomment the next two commands
        # RewriteCond %{HTTPS} !on
        # RewriteRule (.*) https://%{HTTP_HOST}%{REQUEST_URI}
        # --

        RewriteBase /
        RewriteCond %{REQUEST_URI} !.*\.(ico|gif|jpg|jpeg|png|js|json|css)
        RewriteRule ^(.*) index.html [NC,L]
    </IfModule>