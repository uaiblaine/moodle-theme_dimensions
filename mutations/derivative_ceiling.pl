# Decode any source, however large: a 100-megapixel upload then takes the web worker down.
s/if \(\(int\) \$info\['width'\] \* \(int\) \$info\['height'\] > \$maxpixels\) \{/if (false) {/;
