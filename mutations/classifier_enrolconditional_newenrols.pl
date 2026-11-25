# Ignore enrol_conditional's "new enrolments" switch: an instance taking nobody reads open.
s/if \(\(int\) \$instance->customint6 === 0\) \{/if (false) {/;
