# Never cut the rebuilt list: a filtered profile ignores navcourselimit and loses its "View more" link.
s/if \(!\$showall && \$shown == \$CFG->navcourselimit\) \{/if (false) {/;
