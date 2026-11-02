# Offer the reviews' popup whatever the plugin's own rule says: a site that forbids reviews still offers them.
s/\$hasreviewslink = \$reviews > 0 && \\tool_courserating\\permission::can_view_reviews\(\$courseid\);/\$hasreviewslink = \$reviews > 0;/;
