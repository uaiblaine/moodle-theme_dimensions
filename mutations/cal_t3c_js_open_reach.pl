# Let an open course's connectors leave from for ever, not from the end of its 30 days.
s/to: step\.end <= 0 \? from \+ OPEN_DAYS - 1 :/to: step.end <= 0 ? Infinity :/;
