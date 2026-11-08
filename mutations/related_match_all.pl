# MATCH_ALL would keep courses covering only some of the chosen competencies.
s/if \(\$shared < 1 \|\| \(\$manual && \$match === self::MATCH_ALL && \$shared < \$size\)\) \{/if (\$shared < 1) {/;
