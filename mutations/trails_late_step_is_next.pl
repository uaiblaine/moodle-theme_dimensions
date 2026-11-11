# Report a late step as no longer the next one, in the calendar layer's own mapping.
s/'next' => \$enrolled && \$progress\[\$courseid\]\['next'\] === \$step->cmid,/'next' => \$enrolled \&\& \$progress[\$courseid]['next'] === \$step->cmid \&\& (\$state['state'] ?? '') !== progress::LATE,/;
