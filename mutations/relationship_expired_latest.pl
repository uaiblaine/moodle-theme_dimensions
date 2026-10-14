# Keep the first end date seen rather than the latest.
s/\$type === access::RELATIONSHIP_EXPIRED && \(int\) \$state\['endsat'\] > \$entry->endedat\)/\$type === access::RELATIONSHIP_EXPIRED && !\$entry->endedat)/;
