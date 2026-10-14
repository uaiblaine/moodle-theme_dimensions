# Take a day before the start as an end in the end's mode: the period then runs backwards, the
# slip of pressing a start and then an earlier day becomes an error from the web service rather
# than a new start.
s/\} else if \(draftfrom !== '' && iso < draftfrom\) \{/} else if (false) {/;
