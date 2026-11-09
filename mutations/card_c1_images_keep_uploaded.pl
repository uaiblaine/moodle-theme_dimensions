# Ask the renderer for every course, uploaded image or not: the generated cover replaces the course's own.
s/if \(\$image !== null\) \{/if (false) {/;
