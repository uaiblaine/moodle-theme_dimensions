# Take a value outside its vocabulary as a change: the entry then loses what it held for that key.
s/if \(\$valid !== null\) \{/if (true) { \$valid = [\$key => \$changes[\$key]];/;
