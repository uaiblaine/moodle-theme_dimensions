# Shorten the area list the file route, the body classes and the stylesheet read: the compact variant is never served.
s/public const AREAS = \[self::FULL, self::COMPACT\];/public const AREAS = [self::FULL];/;
