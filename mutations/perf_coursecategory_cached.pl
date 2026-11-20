# The cache is never read: every card without an image reads its course's category again, on every request.
s/if \(\$category === false\) \{/if (true) {/;
