# Drop the accessallgroups clause: a grader in a separate groups course never gets the figures, even holding it.
s/\n\s+\|\| has_capability\(\x27moodle\/site:accessallgroups\x27, \$context\);/;/;
