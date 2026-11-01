# Forget that the candidate read stopped at its bound.
s/\$this->truncated = \$surplus \|\| \$bound;/\$this->truncated = \$surplus;/;
