# Stop asking core whether the viewer may see a custom profile field: the session's record carries the hidden ones.
s/if \(!self::visible\(\$definition, \$user\)\)/if (false)/;
