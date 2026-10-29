# choose() stores any flavour id, offered or not.
s/\$flavourid !== self::SITE_DEFAULT && !array_key_exists\(\$flavourid, self::offered\(\)\)/false/;
