# Read guest access behind a key as free access again.
s/return \(\(string\) \$instance->password === ''\) \? self::STATE_FREE : self::STATE_KEY;/return self::STATE_FREE;/;
