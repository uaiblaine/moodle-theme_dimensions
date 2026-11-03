# The default for new courses offers the trail without mod_subcourse.
s/if \(!trail::hides_option\(\$shortname, \$index \+ 1\)\) \{/if (true) {/;
