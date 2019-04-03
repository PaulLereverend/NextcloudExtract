# Extract
Place this app in **nextcloud/apps/**

## Features

* Zip extraction
* Rar extraction (partial)

## Requirements

* Rar PHP extension or unrar 

## Usage

If you want to use this app on external local storage add the mount points on your config.php file like this (no / at the end)
```php
'external' => 
            array (
                0 => '/home/localfoler',
                1 => '/share/anotherfolder',
            ),
```