# Trust any class of the catalogue's name to carry the read method: a catalogue whose method was
# renamed then dies on every category page instead of drawing it without the strip.
s/if \(!class_exists\(\$catalogue\) \|\| !method_exists\(\$catalogue, 'for_viewer'\)\) \{/if (!class_exists(\$catalogue)) {/;
