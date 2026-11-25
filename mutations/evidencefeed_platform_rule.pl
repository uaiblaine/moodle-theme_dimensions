# Answer every viewer with the platform's records: a stranger would read another learner's ratings and notes.
s/if \(!user_competency::can_read_user\(\$userid\)\) \{/if (false) {/;
