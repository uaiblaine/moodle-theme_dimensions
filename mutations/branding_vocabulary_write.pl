# Read a stored colour back RAW, without the sanitiser: a row a migration, a manual write or a
# restore from another site left holding something that is not a colour then reaches the cached
# payload and, through it, the hero's inline style attribute - the one CSS-context sink of the
# feature. This is the guard whose loss an ASSERTION sees (branding_test reads the default back
# out of a poisoned row); the refusals inside validate() only shape the message, since contrast()
# reads any non-colour as 1.0 and the floor refuses the pair anyway.
s/\$row->bgcolour = self::hex\(\(string\) \$row->bgcolour\) \?\? self::DEFAULT_BG;/\$row->bgcolour = (string) \$row->bgcolour;/;
