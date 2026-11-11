# Rename the popover hook the calendar component writes: the reference template and its module still
# say dim-daterange-pop, so the two drift apart while the template test stays green.
s/data-region="dim-daterange-pop"/data-region="dim-daterange-popover"/;
