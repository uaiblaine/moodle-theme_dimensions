# An installed but disabled filter would read as fine.
s/self::installed\(\) && self::enabled\(\) \? recommendation::OK/self::installed() ? recommendation::OK/;
