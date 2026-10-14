# A custom URL that is the site root or index.php sends core's index.php round in a loop.
s/ && !self::is_root_path\(\$url\)//;
