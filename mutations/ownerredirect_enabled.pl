# Send the owner to the overview with competencies disabled, where read_plan() throws instead of core's own words.
s/if \(!\\core_competency\\api::is_enabled\(\)\) \{/if (false) {/;
