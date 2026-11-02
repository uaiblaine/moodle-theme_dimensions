# Forward a week that is not a day.
s/if \(self::is_day\(\$week\)\) \{/if (\$week !== '') {/;
