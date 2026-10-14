# Drop the queue's status clause: an active row on a switched-off instance reads as an application.
s/AND ue\.status <> :active/AND (ue.status <> :active OR 1 = 1)/;
