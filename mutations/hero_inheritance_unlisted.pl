# Name the colour source above whenever core shows it, ignoring local_unlistedcourses: a manager of the child alone
# then reads the name of an unlisted category in the editor's "inherited from" sentence.
s/\$source === null \|\| !category_access::is_category_discoverable\(\$from\)/\$source === null/;
