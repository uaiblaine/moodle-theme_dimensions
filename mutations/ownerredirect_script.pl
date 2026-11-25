# Redirect from any script: tool_lp's other pages, the plans list among them, would leave for the overview.
s/if \(\$script !== self::PLAN_SCRIPT && \$script !== self::COMPETENCY_SCRIPT\) \{/if (false) {/;
