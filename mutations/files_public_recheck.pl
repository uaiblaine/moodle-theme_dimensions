# Serve the file of whatever course the URL names: an unpublished course's image then
# stays reachable, for the internet, through a URL minted while it was public.
s/if \(!publicaccess::is_public\(\$courseid\) && !\(\$filearea === self::AREA_BADGE && self::viewer_may_see\(\$courseid\)\)\) \{/if (false) {/;
