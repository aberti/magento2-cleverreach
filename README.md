![alt tag](https://www.cleverreach.com/images/logo-cleverreach.png)

# CleverReach® Magento 2 plugin
Magento 2 extension is using CleverReach® API (https://rest.cleverreach.com/explorer/v2/).

## Getting started
CleverReach® Magento 2 Integration extension offers synchronization of your Magento 2 newsletter subscribers 
and customers with the CleverReach® subscription groups.  
CleverReach® email marketing tool (http://www.cleverreach.de/) allows you to create professional emails online, 
dispatch them reliably, track their success and manage receivers. CleverReach® is equipped with a particularly 
user-friendly interface, which is used intuitively and without special knowledge.

To setup the synchronization, all you need is your CleverReach® account. You can even create one if you don't have it 
right from the plugin.

## Installation instructions
Plugin can be installed directly from Magento 2 Marketplace.

### Manual installation
Magento 2 module can be installed with Composer (https://getcomposer.org/download/).

To work with this CleverReach® integration, the extension can be installed in a few minutes 
by going through these following steps:

- Step 1: Upload it to your Magento installation root directory
- Step 2: Disable the cache in admin panel under System­ >> Cache Management
- Step 3: Enter the following at the command line (upgrade database):
```
php bin/magento setup:upgrade
```
- Step 4: Enter the following at the command line (deploy static content):
```
php bin/magento setup:static-content:deploy
```
- Step 5: Optionally you might need to fix permissions on your Magento installation
- Step 6: After opening Stores ­> Configuration ­> Advanced ­> Advanced, the module will be shown in the admin panel

After installation is over, CleverReach® configurations can be set by clicking on the CleverReach® icon on 
the main menu on the left side of the screen.

## Version
1.1.0

## Compatibility
Magento 2.0.x, 2.1.x and 2.2.x versions

## Prerequisites
- PHP 5.5 or newer
- MySQL 5.6 or newer (integration tests)