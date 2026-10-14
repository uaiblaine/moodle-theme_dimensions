# Remove the capability-based staff escape: a manager then falls through to the
# relationship test and loses every profile they administer but share no course with.
s/^\s*has_capability\('moodle\/user:viewdetails', \$usercontext\)$/            false/m;
s/^\s*\|\| has_capability\('moodle\/user:viewalldetails', \$usercontext\)$/            || false/m;
