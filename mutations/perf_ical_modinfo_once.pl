# Core's per-event shape again: each event asks modinfo for its own course in date order, so twelve interleaved
# courses evict one another from modinfo's static cache and each is built again.
s/if \(isset\(\$hidden\[\$key\]\)\) \{/if (self::hidden_activity_events([\$key => \$event], \$userid)) {/;
