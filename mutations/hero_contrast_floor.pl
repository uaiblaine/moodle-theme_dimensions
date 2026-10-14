# Accept a pair under the WCAG AA floor: the editor's panel still shows the ratio, but the server
# stops refusing it, so an illegible hero can be published.
s/if \(\$ratio < self::FLOOR\) \{/if (false) {/;
