# Open the class report to a viewer require_login($course) would refuse.
s/if \(!can_access_course\(get_course\(\(int\) \$context->instanceid\), null, '', true\)\) \{/if (false) {/;
