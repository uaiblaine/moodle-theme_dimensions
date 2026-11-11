# Drop the re-read of the offered list: a queued emptying strips a look offered again before cron ran it.
s/if \(array_key_exists\(\(int\) array_key_first\(\$ours\), flavourchoice::offered\(\)\)\) \{/if (false) {/;
