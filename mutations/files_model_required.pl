# Serve the files of a public course that has no hotsite model: the hotsite areas are then
# reachable where the page is not, and a guessed summary file name fetches an image nobody
# published. The cover area keeps its own exemption - it is the gate files_cover_no_model that
# asks whether that exemption is still there.
s/if \(\$filearea !== self::AREA_COVER && !hotsitefields::model\(\$courseid\)\) \{/if (false) {/;
