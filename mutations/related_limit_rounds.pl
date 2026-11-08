# The gate would stop at the first slice, so a refused head would leave the block empty.
s/\$round < self::ROUNDS &&/\$round < 1 \&\&/;
