# The site's switch no longer takes the Trilhas view away.
s/ && self::trails_enabled\(\) && trail::available\(\);/ \&\& trail::available();/;
