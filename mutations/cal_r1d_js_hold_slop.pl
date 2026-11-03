# A held touch that moves (a scroll) still creates.
s/Math\.abs\(x - hold\.x\) > HOLD_SLOP \|\| Math\.abs\(y - hold\.y\) > HOLD_SLOP/false/;
