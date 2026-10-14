# Let a call that asks for the state the course is already in fall through to the event. The write
# still answers correctly and the favourite row is still right, so every other assertion about the
# write stays green - what breaks is the write's own property, one log line per REAL change: a page that
# re-sends the state it already holds (a double click, a retried request, a second tab) then writes
# a second entry into the log saying something changed when nothing did.
s/        \} else \{\n            \/\/ The state asked for is the state held: the answer is the same and nothing moved\.\n            return \$state;\n        \}\n/        }\n/;
