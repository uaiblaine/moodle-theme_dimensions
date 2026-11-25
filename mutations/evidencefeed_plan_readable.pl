# Filter by a plan the viewer may not read: its competencies are revealed through what the filter keeps.
s/if \(!\$plan \|\| !\$plan->can_read\(\)\) \{/if (!\$plan) {/;
