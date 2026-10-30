# Swallow a calendar that could not mount on the account's shell: the reader is told nothing, where the AMD client
# it replaced showed core's exception dialogue. The native date fields still work; the account just loses the word.
s/^    unmounted: exception,\n/    unmounted: nothing,\n/m;
