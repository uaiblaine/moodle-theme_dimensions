# Count the accounts that are deleted or suspended among an event course's participants.
s/AND u\.deleted = 0 AND u\.suspended = 0/AND 1 = 1/;
