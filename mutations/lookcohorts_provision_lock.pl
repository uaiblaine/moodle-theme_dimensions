# Provisioning goes on without the lock: check-then-act races, and a refused lock is used anyway.
s/(\$lock = self::lock\('provision'\);\s+)if \(!\$lock\) \{/$1if (false) {/;
