# Count completion in a course that has it switched off: the card shows a progress the course does not keep.
s/if \(!\$info->is_enabled\(\) \|\| !\$info->is_tracked_user\(\$userid\)\) \{/if (!\$info->is_tracked_user(\$userid)) {/;
