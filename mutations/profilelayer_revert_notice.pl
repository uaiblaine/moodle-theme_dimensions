# Forget core's way back: the log in as notice carries no link back to the real account.
s/\$sorted\['revert'\] = \$path === self::REVERT_PATH \? \$url->out\(false\) : null;/\$sorted['revert'] = null;/;
