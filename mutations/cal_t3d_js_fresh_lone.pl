# A new answer holding one trail keeps the reader's fold of it: a folded trail chosen alone stays folded.
s/if \(trails\.length !== 1 \|\| !\(trails\[0\]\.id in chosen\)\) \{/if (true) {/;
