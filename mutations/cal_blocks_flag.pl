# Take core's own month page (the month template without the block's flag) for the block.
s/return empty\(\$flag\) \? null : self::MONTH;/return self::MONTH;/;
