# Count every enabled plugin left over, manual enrolment included, as a way in through the login.
s/if \(self::overrides_enrol_page_hook\(\$plugins\[\$type\]\)\) \{/if (true) {/
