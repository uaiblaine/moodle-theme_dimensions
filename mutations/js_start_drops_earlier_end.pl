# Keep an end that comes before a newly pressed start: the boxes then describe a period running
# backwards, which the web service refuses, so "Done" turns into an error instead of a listing.
s/if \(draftto !== '' && draftto < iso\) \{/if (false) {/;
