# Hand a nested key member on to the cleaner, which an anonymous page cannot survive.
s/\$members = array_filter\(\$raw, static fn\(\$member\): bool => is_scalar\(\$member\)\);/\$members = \$raw;/;
