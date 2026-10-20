# Drop the id guard of the submission hook, so data without a course id reaches the write.
s/if \(empty\(\$data->id\)\) \{/if (false) {/;
