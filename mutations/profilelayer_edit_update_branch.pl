# Skip core's first branch: an account with moodle/user:update is sent to edit.php instead of editadvanced.php.
s/if \(has_capability\('moodle\/user:update', \$system\)\) \{/if (false) {/;
