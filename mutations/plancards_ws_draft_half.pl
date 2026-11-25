# Ask for the right to read the plans alone, refusing a viewer core lists the drafts to.
s/if \(!plan::can_read_user\(\$userid\) && !plan::can_read_user_draft\(\$userid\)\) \{/if (!plan::can_read_user(\$userid)) {/;
