# Stop asking for the course list node: the renderer prints core's list as it came.
s/if \(\(string\) \$node->name === profilecourses::NODE\) \{/if (false) {/;
