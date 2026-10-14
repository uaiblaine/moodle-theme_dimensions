# Read the unset core setting as off, so a site that has not stored it, where core's default is on, is warned for nothing.
s/\$CFG->forceloginforprofiles \?\? 1\)/\$CFG->forceloginforprofiles ?? 0)/;
