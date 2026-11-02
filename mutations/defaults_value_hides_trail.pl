# A stored trail default makes new courses trails without mod_subcourse.
s/ \|\| trail::hides_option\(\$shortname, \$value\)\) \{/) {/;
