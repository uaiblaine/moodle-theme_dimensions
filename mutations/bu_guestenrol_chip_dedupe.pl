# Beside the guest entry the guest enrolment chip is not repeated.
s/            if \(!isset\(\$notice->chips\[self::KEY_GUEST\]\)\) \{/            if (true) {/;
