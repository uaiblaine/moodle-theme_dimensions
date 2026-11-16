# Drop the own-profile capability from the refusal: a person whose role prohibits editing their profile gets the link.
s/!has_capability\('moodle\/user:editownprofile', \$system\)\n(\s*)&& /false\n$1&& /;
