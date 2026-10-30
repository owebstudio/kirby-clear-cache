![Clear Cache, a Kirby plugin by oweb studio. Clear the cache, media and temporary files from the Panel.](.github/cover.png)

# Kirby Clear Cache

This plugin allows you to clean temporary files and folders such as cache, media, .lock and .logins on your website.

## Installation

1. Download the latest release
2. Unzip downloaded file
3. Copy/paste unzipped folder in your `/site/plugins` folder

## Permissions

Admins can always clear the cache. Other roles need the `owebstudio.clear-cache.clear` permission in their user blueprint:

```yaml
# site/blueprints/users/editor.yml
title: Editor
permissions:
  owebstudio.clear-cache:
    clear: true
```

To hide the plugin from a role, including admins, disable its area:

```yaml
permissions:
  access:
    clear-cache: false
```
