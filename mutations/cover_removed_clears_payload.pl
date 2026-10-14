# Skip an EMPTY draft area as well as a zero draft id, which undoes the removal path:
# the form still posts a real draft holding nothing when the manager deletes the file,
# and save() then leaves the stored area exactly as it was. The page reports itself saved, the
# form reopens with the file back, the cached payload still names it and the card still draws
# it - the quietest possible failure.
s/if \(!\$draft\) \{/if (!\$draft || !file_get_draft_area_info(\$draft)['filecount']) {/;
