# Drop the queue's end-date clause: an approved row re-suspended past its end reads as an application
# still waiting.
s/AND \(ue\.timeend = 0 OR ue\.timeend > :now\)/AND (ue.timeend = 0 OR ue.timeend > :now OR 1 = 1)/;
