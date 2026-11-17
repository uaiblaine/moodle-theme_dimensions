# Ignore the icon a node declares: every destination the table gives no icon falls to the generic link (D101).
s/if \(\$declared instanceof pix_icon && /if (false && \$declared instanceof pix_icon && /;
