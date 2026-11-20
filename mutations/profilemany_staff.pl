# Drop the batch's staff escape: a mentor or manager loses the profiles they may see.
s/\n\s*has_capability\('moodle\/user:viewdetails', \$subjectcontext\)\n\s*\|\| has_capability\('moodle\/user:viewalldetails', \$subjectcontext\)/ false/
