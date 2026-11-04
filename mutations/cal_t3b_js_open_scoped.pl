# Open a course's popover on any bar of its activity, whatever trail it is in.
s/`\[data-trail="\$\{trailid\}"\] \[data-region="dim-cal-trailstep"\]\[data-cmid="\$\{cmid\}"\]`/`[data-region="dim-cal-trailstep"][data-cmid="\${cmid}"]`/;
