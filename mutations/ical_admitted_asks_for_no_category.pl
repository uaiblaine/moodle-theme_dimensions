# Breaks ical::admitted(): an event naming no category (categoryid 0) is collected as a category id, so core is asked about category 0.
s/if \(!empty\(\$event->categoryid\)\) \{/if (true) {/;
