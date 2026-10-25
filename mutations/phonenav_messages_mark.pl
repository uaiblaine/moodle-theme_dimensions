# Offer the messages entry whether or not message_popup printed its region.
s/if \(str_contains\(\$pluginoutput, self::MESSAGES_MARK\)\) \{/if (true) {/;
