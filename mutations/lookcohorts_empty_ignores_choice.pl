# Empty from the snapshot alone: a person who chose a look offered again during the run is stripped from it.
s/if \(\$member && self::recorded_choice\(\$userid\) !== \$flavourid\) \{/if (\$member) {/;
