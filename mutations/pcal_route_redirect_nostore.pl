# Let a cache keep the way from an old token to the current one.
s/return self::uncached\(self::redirect\(\$response, self::url\(\$half, \$current, \$lang, \$from, \$to\)\)\);/return self::redirect(\$response, self::url(\$half, \$current, \$lang, \$from, \$to));/;
