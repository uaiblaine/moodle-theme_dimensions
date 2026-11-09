# Read completion in a course that turned it off: its old rows say nothing any more.
s/if \(\(new \\completion_info\(\$course\)\)->is_enabled\(\)\) \{/if (true) {/;
