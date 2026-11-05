# Any relationship keeps a trail, a suspended or an ended one included.
s/if \(in_array\(\$type, self::KEPT_RELATIONSHIPS, true\)\) \{/if (\$type !== access::RELATIONSHIP_NONE) {/;
