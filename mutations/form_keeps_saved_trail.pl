# A course saved as a trail loses its choice in the form, and the form no longer shows what the course holds.
s/if \(!empty\(\$course->id\) && landingfields::model\(\(int\) \$course->id\) === landingfields::MODEL_TRAIL\) \{/if (false) {/;
