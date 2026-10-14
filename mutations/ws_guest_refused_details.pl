# Let the guest account through the details batch. Third of the three guest gates: the batch is
# the one function whose answer is per course and silently partial, so a lost refusal here shows
# as an empty list rather than as an error, which is what makes the gate worth its line.
s/if \(isguestuser\(\)\) \{/if (false) {/;
