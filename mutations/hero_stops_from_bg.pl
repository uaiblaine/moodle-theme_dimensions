# Draw every band in the default navy whatever colour the category carries: the stored bgcolour
# stops reaching the gradient, and a category branded in its own colour renders in the default blue.
s/\$channels = self::channels\(\$bg\) \?\? self::channels\(self::DEFAULT_BG\);/\$channels = self::channels(self::DEFAULT_BG);/;
