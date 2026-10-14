# Take a share picture as the source even when no derivative can be made of it: a vector file then
# leaves the shared link with no image at all instead of the course image.
s/if \(\$share !== null && self::derivative\(\$share, \$maxpixels\) !== null\) \{/if (\$share !== null) {/;
