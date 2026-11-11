# Let a profile override the caller's own option.
s/return \$options \+ self::PROFILES\[\$profile\];/return self::PROFILES[\$profile] + \$options;/;
