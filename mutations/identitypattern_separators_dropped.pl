# Let every character of the value fill a slot: a value stored with its separators no longer fits its pattern.
s/\[\\p\{L\}\\p\{N\}\]/./;
