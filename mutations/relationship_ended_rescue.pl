# Let every relationship but enrolled and none set the rescue's pending flags: suspended and expired rescue.
s/\} else if \(\$type === access::RELATIONSHIP_PENDING \|\| \$type === access::RELATIONSHIP_WAITLISTED\) \{/} else if (\$type !== access::RELATIONSHIP_ENROLLED && \$type !== access::RELATIONSHIP_NONE) {/;
