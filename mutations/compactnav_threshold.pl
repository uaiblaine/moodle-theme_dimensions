# Arm a navigation of MAXHIDDEN items, which can never send more than MAXHIDDEN into "More".
s/if \(self::candidates\(\$moremenu\) <= self::MAXHIDDEN\) \{/if (self::candidates(\$moremenu) < self::MAXHIDDEN) {/;
