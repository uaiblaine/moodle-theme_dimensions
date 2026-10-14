# Let an overtaken request fall back. A visitor who typed on after a slow request failed would be
# sent to the address of the term they had already replaced.
s/if \(ticket === seq\) \{\n            fallBack\(/if (ticket >= 0) {\n            fallBack(/;
