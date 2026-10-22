# Treat a drawer as lying over the page up to core's isLarge() (1400px): an open course index holds the bar from 991 on.
s/const overlaid = isSmall\(\);/const overlaid = isSmall() || window.innerWidth < 1400;/;
