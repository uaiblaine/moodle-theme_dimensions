# Take any truthy value as the stored toggle: a hand-edited 1, "yes" or "true" is then a remembered
# state the vocabulary never allowed, and a refused write of one replaces what the entry held.
s/\(\$raw\[self::KEY_FAVOURITES\] \?\? null\) === true/!empty(\$raw[self::KEY_FAVOURITES])/;
