# Let a profile override the caller's own option.
s/return \$options \+ \$profileoptions;/return \$profileoptions + \$options;/;
