# The site default gets no favicon of its own: it is looked up among the flavours' marks.
s/if \(\$id === self::SITE_DEFAULT\) \{/if (false) {/;
