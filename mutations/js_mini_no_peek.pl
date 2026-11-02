# Arm the shell's per-card behaviour on the panel's course cards too. The observer then asks for a mini's
# progress a second time beside the panel's own batch.
s/\.filter\(\(card\) => !card\.closest\(HOOKS\.areapanel\)\)/.filter((card) => card !== null)/;
