# Name the unlisted category above the course to every reader of the notice: a teacher of the course alone, who may
# not discover that category, then reads its name.
s/if \(\\local_unlistedcourses\\category_access::is_category_discoverable\(\(int\) \$unlisted->id\)\) \{/if (true) {/;
