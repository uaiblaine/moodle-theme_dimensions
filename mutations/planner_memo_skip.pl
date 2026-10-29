# Ignore the failure memo: a flavour whose SCSS does not compile costs a whole compile every minute.
s/if \(\(\$failed\["\{\$flavourid\}\/\{\$direction\}"\] \?\? null\) === "\{\$rev\}_\{\$subrev\}"\)/if (false)/;
