# The day view is never read: view=day is the month.
s/if \(self::scalar_param\(self::PARAM_VIEW\) === self::VIEW_DAY\) \{/if (false) {/;
