# Show a status to a person completion does not track: a teacher reads "Pending" on every activity.
s/if \(!\$details->has_completion\(\) \|\| !\$details->is_tracked_user\(\)\) \{/if (!\$details->has_completion()) {/;
