# Tell the component an answer as a pending question instead: the open panel keeps answering the
# criteria it was opened with.
s/new CustomEvent\(EVENTS\.criteria, \{detail: \{\n        accordion:/new CustomEvent(EVENTS.pending, {detail: {\n        accordion:/;
