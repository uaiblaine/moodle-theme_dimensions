# Arm the shell's per-card behaviour on the panel's course cards too. The hover peek then opens the copy of a
# disclosure the mini keeps out of reach, beside a card in a rail that clips it, and the observer asks for a mini's
# progress a second time beside the panel's own batch.
s/\.filter\(\(card\) => !card\.closest\(HOOKS\.areapanel\)\)/.filter((card) => card !== null)/;
