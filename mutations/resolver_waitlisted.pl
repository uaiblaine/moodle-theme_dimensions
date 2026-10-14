# Read a waiting-list application as any other pending one.
s/\$waitlisted \? access::RELATIONSHIP_WAITLISTED : access::RELATIONSHIP_PENDING/access::RELATIONSHIP_PENDING/;
