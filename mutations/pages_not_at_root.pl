# Ask the catalogue at the root as well: the course index would draw a strip it must not carry.
s/if \(\$categoryid <= 0\) \{\n            return null;/if (false) {\n            return null;/;
