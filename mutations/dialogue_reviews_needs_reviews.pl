# Offer the reviews' popup for a course with no review: the plugin's popup opens on nothing to read.
s/\$hasreviewslink = \$reviews > 0 && \\tool_courserating/\$hasreviewslink = \\tool_courserating/;
