# The services no longer ask the class report's rule before acting: the preference's writer stores for anybody.
s/if \(!classreport::may_view\(\$context\)\) \{/if (false) {/;
