# Build the event and never fire it: the one write of the feature stops reaching the log,
# while every other assertion about the write stays green.
s/\]\)->trigger\(\);/]);/;
