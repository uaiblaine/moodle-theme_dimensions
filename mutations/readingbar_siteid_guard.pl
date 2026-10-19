# Read the front page as a course: its activities and home get the reading bar.
s/if \(!\$course \|\| \(int\) \$course->id === \(int\) SITEID\) \{/if (!\$course) {/;
