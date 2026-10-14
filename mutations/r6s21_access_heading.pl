# Keep the enrolment heading over a card whose only way in is guest access.
s/'isguestaccess' => \$state === resolution::STATE_VISITOR && /'isguestaccess' => false \&\& /
