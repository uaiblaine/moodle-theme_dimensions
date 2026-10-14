# Make the cover area ask for a hotsite model like the hotsite's own areas: the cover of every
# public course that chose no model then refuses, which is most of a public category's listing -
# the defect the area exists to fix, back with a green suite.
s/if \(\$filearea !== self::AREA_COVER && !hotsitefields::model\(\$courseid\)\) \{/if (!hotsitefields::model(\$courseid)) {/;
