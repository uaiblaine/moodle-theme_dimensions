# A choice moves memberships without the per-person lock.
s/if \(!\$lock\) \{(\s+)return;/if (false) {$1return;/;
