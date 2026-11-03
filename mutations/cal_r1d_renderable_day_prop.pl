# The props never say the day view: the client draws the month.
s/if \(\$view === page::VIEW_DAY && \$day !== ''\) \{/if (false) {/;
