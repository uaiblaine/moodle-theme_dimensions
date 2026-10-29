# Ignore the dry run: --dry-run compiles every missing sheet.
s/if \(\$dryrun\) \{/if (false) {/;
