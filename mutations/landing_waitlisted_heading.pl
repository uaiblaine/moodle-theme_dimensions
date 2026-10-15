# Draw the pending heading above the waiting list's own.
s/'ispending' => \$state === resolution::STATE_PENDING && !\$iswaitlisted,/'ispending' => \$state === resolution::STATE_PENDING,/;
