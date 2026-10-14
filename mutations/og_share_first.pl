# Never take the share picture as the Open Graph source: the tags and the og area go back to the
# course image whatever an editor chose.
s/if \(\$share !== null && self::derivative\(\$share, \$maxpixels\) !== null\) \{/if (false) {/;
